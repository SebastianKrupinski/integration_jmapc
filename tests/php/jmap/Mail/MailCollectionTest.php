<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Jmap\Mail;

use JmapClient\Client;
use OCA\JMAPC\Objects\Mail\MailCollectionObject;
use OCA\JMAPC\Service\Remote\RemoteMailService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Tests\Jmap\TestClientFactory;
use OCA\JMAPC\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('DB')]
class MailCollectionTest extends TestCase {

	private Client $remoteClient;
	private RemoteMailService $mailService;

	public function setUp(): void {
		parent::setUp();
		TestClientFactory::checkRequirements($this);
		$this->remoteClient = TestClientFactory::InstanceClient();
		$this->mailService = RemoteService::mailService($this->remoteClient);
	}

	public function tearDown(): void {
		parent::tearDown();

		if (!isset($this->mailService)) {
			return;
		}

		$collections = $this->mailService->collectionList();

		foreach ($collections as $collection) {
			if (str_starts_with($collection->getLabel(), 'Test Mail Collection ')
				|| str_starts_with($collection->getLabel(), 'Modified Mail Collection ')
				|| str_starts_with($collection->getLabel(), 'To Delete Mail Collection ')) {
				$this->mailService->collectionDelete($collection->id());
			}
		}
	}

	public function testCollectionCreate(): void {
		// create a collection
		$collection = $this->mailService->collectionFresh();
		$collection->setLabel('Test Mail Collection ' . time());
		$collectionId = $this->mailService->collectionCreate(null, $collection);
		$this->assertNotEmpty($collectionId);
		$this->assertIsString($collectionId);
	}

	public function testCollectionModify(): void {
		// create a collection
		$collection = $this->mailService->collectionFresh();
		$collection->setLabel('Test Mail Collection ' . time());
		$collectionId = $this->mailService->collectionCreate(null, $collection);
		$this->assertIsString($collectionId);

		// modify the collection
		$collection->setLabel('Modified Mail Collection ' . time());
		$result = $this->mailService->collectionModify($collectionId, $collection);
		$this->assertNotEmpty($result);
		$this->assertEquals($collectionId, $result);
	}

	public function testCollectionDelete(): void {
		// create a collection
		$collection = $this->mailService->collectionFresh();
		$collection->setLabel('Test Mail Collection ' . time());
		$collectionId = $this->mailService->collectionCreate(null, $collection);
		$this->assertNotEmpty($collectionId);
		$this->assertIsString($collectionId);

		// delete the collection
		$result = $this->mailService->collectionDelete($collectionId);
		$this->assertNotEmpty($result);
		$this->assertEquals($collectionId, $result);
	}

	public function testCollectionFetch(): void {
		// create a collection
		$collection = $this->mailService->collectionFresh();
		$collection->setLabel('Test Mail Collection ' . time());
		$collectionId = $this->mailService->collectionCreate(null, $collection);
		$this->assertNotEmpty($collectionId);
		$this->assertIsString($collectionId);

		// fetch the created collection
		$fetchedCollection = $this->mailService->collectionFetch($collectionId);
		$this->assertNotNull($fetchedCollection);
		$this->assertInstanceOf(MailCollectionObject::class, $fetchedCollection);
		$this->assertEquals($collectionId, $fetchedCollection->id());
	}

	public function testCollectionList(): void {
		// list the collections
		$collections = $this->mailService->collectionList();
		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		$this->assertInstanceOf(MailCollectionObject::class, reset($collections));
	}

	public function testCollectionListWithFilter(): void {
		// construct a filter condition
		$filter = $this->mailService->collectionListFilter();
		$filter->condition('name', 'Inbox');
		// fetch collections with the filter
		$collections = $this->mailService->collectionList(null, $filter);
		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		$this->assertCount(1, $collections);
		$this->assertInstanceOf(MailCollectionObject::class, reset($collections));
	}

	public function testCollectionListWithSort(): void {
		// construct a ascending sort condition
		$sort = $this->mailService->collectionListSort();
		$sort->condition('name', true);
		// fetch collections with the sort
		$collections = $this->mailService->collectionList(null, null, $sort);
		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		$this->assertInstanceOf(MailCollectionObject::class, reset($collections));

		$collectionAsc = reset($collections);

		// construct a descending sort condition
		$sort = $this->mailService->collectionListSort();
		$sort->condition('name', false);
		// fetch collections with the sort
		$collections = $this->mailService->collectionList(null, null, $sort);
		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		$this->assertInstanceOf(MailCollectionObject::class, end($collections));

		$collectionDesc = end($collections);

		$this->assertEquals($collectionAsc->id(), $collectionDesc->id());
	}
}
