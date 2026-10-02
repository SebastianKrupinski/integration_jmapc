<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Integration\Events;

use JmapClient\Client;
use OCA\JMAPC\Objects\Event\EventCollectionObject;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Tests\Integration\TestClientFactory;
use OCA\JMAPC\Tests\Unit\TestCase;

class EventsCollectionTest extends TestCase {

	private Client $remoteClient;
	private RemoteEventsService $eventsService;

	public function setUp(): void {
		parent::setUp();
		$this->remoteClient = TestClientFactory::InstanceClient();
		$this->eventsService = RemoteService::eventsService($this->remoteClient);
	}

	public function tearDown(): void {
		parent::tearDown();

		$collections = $this->eventsService->collectionList();

		foreach ($collections as $collection) {
			if (str_starts_with($collection->Label, 'Test Event Collection ')
				|| str_starts_with($collection->Label, 'Modified Event Collection ')
				|| str_starts_with($collection->Label, 'To Delete Event Collection ')) {
				$this->eventsService->collectionDelete($collection->Id);
			}
		}
	}

	public function testCollectionCreate(): void {
		// create a collection
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);
		$this->assertIsString($collectionId);
	}

	public function testCollectionModify(): void {
		// create a new collection
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// modify the collection
		$collection->Label = 'Modified Event Collection ' . time();
		$result = $this->eventsService->collectionModify($collectionId, $collection);
		$this->assertNotEmpty($result);
		$this->assertEquals($collectionId, $result);
	}

	public function testCollectionDelete(): void {
		// create a new collection
		$collection = new EventCollectionObject();
		$collection->Label = 'To Delete Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// delete the collection
		$result = $this->eventsService->collectionDelete($collectionId);
		$this->assertNotEmpty($result);
		$this->assertEquals($collectionId, $result);
	}

	public function testCollectionFetch(): void {
		// create a new collection
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// fetch the created collection
		$fetchedCollection = $this->eventsService->collectionFetch($collectionId);
		$this->assertNotNull($fetchedCollection);
		$this->assertInstanceOf(EventCollectionObject::class, $fetchedCollection);
		$this->assertEquals($collectionId, $fetchedCollection->Id);
	}

	public function testCollectionList(): void {
		$collections = $this->eventsService->collectionList();

		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		$this->assertInstanceOf(EventCollectionObject::class, reset($collections));
	}

}
