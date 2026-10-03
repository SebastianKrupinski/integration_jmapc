<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Store\Local;

use OCA\JMAPC\Store\Local\FileCollectionEntity;
use OCA\JMAPC\Store\Local\FilesStore;
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;

#[Group('DB')]
class FilesStoreTest extends TestCase {
	private const USER_A = 'jmapc-files-test-a';
	private const USER_B = 'jmapc-files-test-b';

	private FilesStore $store;

	protected function setUp(): void {
		parent::setUp();
		$this->store = new FilesStore(Server::get(IDBConnection::class));
		$this->cleanUp();
	}

	protected function tearDown(): void {
		$this->cleanUp();
		parent::tearDown();
	}

	private function cleanUp(): void {
		$this->store->collectionDeleteByUser(self::USER_A);
		$this->store->collectionDeleteByUser(self::USER_B);
		$cmd = Server::get(IDBConnection::class)->getQueryBuilder();
		$cmd->delete('jmapc_services')
			->where($cmd->expr()->in('uid', $cmd->createNamedParameter([self::USER_A, self::USER_B], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeStatement();
	}

	private function createService(string $uid, bool $enabled, bool $connected): int {
		$cmd = Server::get(IDBConnection::class)->getQueryBuilder();
		$cmd->insert('jmapc_services')
			->values([
				'uid' => $cmd->createNamedParameter($uid),
				'uuid' => $cmd->createNamedParameter(uniqid('jmapc-test-', true)),
				'location_protocol' => $cmd->createNamedParameter('https'),
				'location_host' => $cmd->createNamedParameter('example.org'),
				'location_port' => $cmd->createNamedParameter(443, IQueryBuilder::PARAM_INT),
				'location_security' => $cmd->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
				'auth' => $cmd->createNamedParameter('BA'),
				'address_primary' => $cmd->createNamedParameter($uid . '@example.org'),
				'enabled' => $cmd->createNamedParameter($enabled, IQueryBuilder::PARAM_BOOL),
				'connected' => $cmd->createNamedParameter($connected, IQueryBuilder::PARAM_BOOL),
			])
			->executeStatement();
		return $cmd->getLastInsertId();
	}

	private function createCollection(string $uid, int $sid, string $location, bool $visible = true, string $mode = 'live'): FileCollectionEntity {
		$collection = new FileCollectionEntity();
		$collection->setUid($uid);
		$collection->setSid($sid);
		$collection->setUuid(uniqid('jmapc-test-', true));
		$collection->setLabel('Label ' . $sid);
		$collection->setLocation($location);
		$collection->setMode($mode);
		$collection->setVisible($visible);
		return $this->store->collectionCreate($collection);
	}

	public function testCreateAndFetch(): void {
		$created = $this->createCollection(self::USER_A, 101, '/JMAP');

		$fetched = $this->store->collectionFetch($created->getId());

		self::assertNotNull($fetched);
		self::assertSame(self::USER_A, $fetched->getUid());
		self::assertSame(101, $fetched->getSid());
		self::assertNull($fetched->getCcid());
		self::assertSame($created->getUuid(), $fetched->getUuid());
		self::assertSame('Label 101', $fetched->getLabel());
		self::assertSame('/JMAP', $fetched->getLocation());
		self::assertSame('live', $fetched->getMode());
		self::assertTrue($fetched->getVisible());
	}

	public function testFetchMissing(): void {
		self::assertNull($this->store->collectionFetch(PHP_INT_MAX));
	}

	public function testHiddenRoundTrip(): void {
		$created = $this->createCollection(self::USER_A, 101, '/JMAP', false);

		self::assertFalse($this->store->collectionFetch($created->getId())->getVisible());
	}

	public function testListFilteredAndIndexedById(): void {
		$first = $this->createCollection(self::USER_A, 101, '/One');
		$second = $this->createCollection(self::USER_A, 102, '/Two');
		$this->createCollection(self::USER_B, 103, '/Three');

		$filter = $this->store->collectionListFilter();
		$filter->condition('uid', self::USER_A);
		$collections = $this->store->collectionList($filter);

		self::assertSame([$first->getId(), $second->getId()], array_keys($collections));
		self::assertSame('/Two', $collections[$second->getId()]->getLocation());
	}

	public function testModify(): void {
		$created = $this->createCollection(self::USER_A, 101, '/JMAP');
		$created->setLocation('/Moved');
		$created->setVisible(false);

		$this->store->collectionModify($created);

		$fetched = $this->store->collectionFetch($created->getId());
		self::assertSame('/Moved', $fetched->getLocation());
		self::assertFalse($fetched->getVisible());
	}

	public function testDelete(): void {
		$created = $this->createCollection(self::USER_A, 101, '/JMAP');

		$this->store->collectionDelete($created);

		self::assertNull($this->store->collectionFetch($created->getId()));
	}

	public function testDeleteByService(): void {
		$removed = $this->createCollection(self::USER_A, 101, '/One');
		$kept = $this->createCollection(self::USER_A, 102, '/Two');

		self::assertSame(1, $this->store->collectionDeleteByService(101));

		self::assertNull($this->store->collectionFetch($removed->getId()));
		self::assertNotNull($this->store->collectionFetch($kept->getId()));
	}

	public function testDeleteByUser(): void {
		$this->createCollection(self::USER_A, 101, '/One');
		$this->createCollection(self::USER_A, 102, '/Two');
		$kept = $this->createCollection(self::USER_B, 103, '/Three');

		self::assertSame(2, $this->store->collectionDeleteByUser(self::USER_A));

		$filter = $this->store->collectionListFilter();
		$filter->condition('uid', self::USER_A);
		self::assertSame([], $this->store->collectionList($filter));
		self::assertNotNull($this->store->collectionFetch($kept->getId()));
	}

	public function testDeleteLeavesSharedCollectionsUntouched(): void {
		$db = Server::get(IDBConnection::class);
		$count = static function () use ($db): int {
			$cmd = $db->getQueryBuilder();
			$cmd->select($cmd->func()->count('*'))->from('jmapc_collections');
			return (int)$cmd->executeQuery()->fetchOne();
		};
		$before = $count();
		$this->createCollection(self::USER_A, 101, '/One');

		$this->store->collectionDeleteByService(101);
		$this->store->collectionDeleteByUser(self::USER_A);

		self::assertSame($before, $count());
	}

	public function testListActive(): void {
		$active = $this->createService(self::USER_A, true, true);
		$disabled = $this->createService(self::USER_A, false, true);
		$disconnected = $this->createService(self::USER_A, true, false);
		$other = $this->createService(self::USER_B, true, true);
		$expected = $this->createCollection(self::USER_A, $active, '/Active');
		$this->createCollection(self::USER_A, $active, '/Hidden', false);
		$this->createCollection(self::USER_A, $active, '/Cached', true, 'cached');
		$this->createCollection(self::USER_A, $disabled, '/ServiceDisabled');
		$this->createCollection(self::USER_A, $disconnected, '/ServiceDisconnected');
		$this->createCollection(self::USER_B, $other, '/OtherUser');

		$collections = $this->store->collectionListActive(self::USER_A, 'live');

		self::assertSame([$expected->getId()], array_keys($collections));
		self::assertSame('/Active', $collections[$expected->getId()]->getLocation());
	}
}
