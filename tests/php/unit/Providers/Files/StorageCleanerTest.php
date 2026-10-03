<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Providers\Files;

use OC\Files\Cache\Storage as StorageCache;
use OCA\JMAPC\Providers\Files\Live\Storage;
use OCA\JMAPC\Providers\Files\StorageCleaner;
use OCA\JMAPC\Service\Remote\RemoteFilesService;
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;

#[Group('DB')]
class StorageCleanerTest extends TestCase {
	private const REMOVED = 999999001;
	private const KEPT = 999999002;

	private StorageCleaner $cleaner;

	protected function setUp(): void {
		parent::setUp();
		$this->cleaner = new StorageCleaner(Server::get(IDBConnection::class));
	}

	protected function tearDown(): void {
		$this->cleaner->remove(Storage::storageId(self::REMOVED));
		$this->cleaner->remove(Storage::storageId(self::KEPT));
		parent::tearDown();
	}

	private function populate(int $collectionId): int {
		$storage = new Storage(['cid' => $collectionId, 'sid' => 0, 'remote' => $this->createMock(RemoteFilesService::class)]);
		$cache = $storage->getCache();
		$cache->put('', ['size' => 0, 'mtime' => 1, 'storage_mtime' => 1, 'mimetype' => 'httpd/unix-directory']);
		$cache->put('a.txt', ['size' => 1, 'mtime' => 1, 'storage_mtime' => 1, 'mimetype' => 'text/plain']);
		return $storage->getStorageCache()->getNumericId();
	}

	private function cacheEntries(int $numericId): int {
		$cmd = Server::get(IDBConnection::class)->getQueryBuilder();
		$cmd->select($cmd->func()->count('*'))
			->from('filecache')
			->where($cmd->expr()->eq('storage', $cmd->createNamedParameter($numericId, IQueryBuilder::PARAM_INT)));
		return (int)$cmd->executeQuery()->fetchOne();
	}

	public function testRemovesOnlyTheGivenStorage(): void {
		$removed = $this->populate(self::REMOVED);
		$kept = $this->populate(self::KEPT);
		self::assertSame(2, $this->cacheEntries($removed));

		$this->cleaner->remove(Storage::storageId(self::REMOVED));

		self::assertSame(0, $this->cacheEntries($removed));
		self::assertNull(StorageCache::getStorageById(Storage::storageId(self::REMOVED)));
		self::assertSame(2, $this->cacheEntries($kept));
		self::assertNotNull(StorageCache::getStorageById(Storage::storageId(self::KEPT)));
	}

	public function testUnknownStorageIsIgnored(): void {
		$this->cleaner->remove('jmapc::files::does-not-exist');
		$this->addToAssertionCount(1);
	}
}
