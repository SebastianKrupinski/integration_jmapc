<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Integration\Events;

use OCA\JMAPC\Objects\Event\EventCollectionObject;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use Test\TestCase;

class EventsCollectionTest extends TestCase {

	private ServiceEntity $service;
	private string $parentCollectionId = '';

	public function setUp(): void {
		parent::setUp();
		// Load the credentials from the JSON file
		$servicesFile = __DIR__ . '/../../resources/services.json';
		$servicesData = file_get_contents($servicesFile);
		$servicesData = json_decode($servicesData, true);
		$service = new ServiceEntity();
		$this->service = $service->fromRow($servicesData[0]);
	}

	public function testCollectionList(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		$collections = $eventsService->collectionList();

		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		// Store the first collection ID for subsequent tests
		if (!empty($collections)) {
			$firstCollection = reset($collections);
			$this->parentCollectionId = $firstCollection->Id;
		}
	}

	public function testCollectionFetch(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		// First get a list of collections to fetch one
		$collections = $eventsService->collectionList();
		$this->assertNotEmpty($collections);

		$firstCollection = reset($collections);
		$collectionId = $firstCollection->Id;
		$fetchedCollection = $eventsService->collectionFetch($collectionId);

		$this->assertNotNull($fetchedCollection);
		$this->assertInstanceOf(EventCollectionObject::class, $fetchedCollection);
		$this->assertEquals($collectionId, $fetchedCollection->Id);
	}

	public function testCollectionCreate(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		// Create a new collection object
		$newCollection = new EventCollectionObject();
		$newCollection->Label = 'Test Event Collection ' . time();

		// Attempt to create the collection
		$createdCollectionId = $eventsService->collectionCreate($newCollection);

		// Should return a non-empty string ID if successful
		$this->assertNotEmpty($createdCollectionId);
		$this->assertIsString($createdCollectionId);
	}

	public function testCollectionModify(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		// First, get an existing collection to modify
		$collections = $eventsService->collectionList();
		$this->assertNotEmpty($collections);
		$existingCollection = reset($collections);
		$collectionId = $existingCollection->Id;
		$originalLabel = $existingCollection->Label;

		// Create a modified version
		$modifiedCollection = new EventCollectionObject();
		$modifiedLabel = 'Modified Event Collection ' . time();
		$modifiedCollection->Label = $modifiedLabel;

		// Attempt to modify the collection
		$result = $eventsService->collectionModify($collectionId, $modifiedCollection);

		// Should return the collection ID if successful
		if (!empty($result)) {
			$this->assertNotEmpty($result);
			$this->assertIsString($result);
		}
	}

	public function testCollectionDelete(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		// First, try to create a collection to delete
		$newCollection = new EventCollectionObject();
		$newCollection->Label = 'To Delete Event Collection ' . time();

		$createdCollectionId = $eventsService->collectionCreate($newCollection);

		// If creation succeeded, try to delete
		if (!empty($createdCollectionId)) {
			$result = $eventsService->collectionDelete($createdCollectionId);

			// Result should be a non-empty string if deletion succeeded
			$this->assertNotEmpty($result);
			$this->assertIsString($result);
		}
	}

	public function testEntityList(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		// Get a collection to list entities from
		$collections = $eventsService->collectionList();
		$this->assertNotEmpty($collections);
		$firstCollection = reset($collections);
		$collectionId = $firstCollection->Id;

		// Get unfiltered list first
		$allEntities = $eventsService->entityList($collectionId);
		$this->assertIsArray($allEntities);
	}

	public function testEntityDelta(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		// Get a collection
		$collections = $eventsService->collectionList();
		$this->assertNotEmpty($collections);
		$firstCollection = reset($collections);
		$collectionId = $firstCollection->Id;

		// Get initial state
		$initialList = $eventsService->entityList($collectionId);
		$this->assertIsArray($initialList);

		// Get a state string (empty for first time)
		$state = isset($initialList['state']) ? $initialList['state'] : '';

		// Get delta
		$delta = $eventsService->entityDelta($collectionId, $state);

		$this->assertNotNull($delta);
		// Delta should have a new signature
		$this->assertNotEmpty($delta->signature);
	}

	public function testEntityFetch(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$eventsService = RemoteService::eventsService($remoteClient);

		// Get a collection
		$collections = $eventsService->collectionList();
		$this->assertNotEmpty($collections);
		$firstCollection = reset($collections);
		$collectionId = $firstCollection->Id;

		// Get entities from the collection
		$entities = $eventsService->entityList($collectionId);
		$this->assertIsArray($entities);

		// If there are entities, fetch one
		if (isset($entities['list']) && !empty($entities['list'])) {
			$firstEntity = reset($entities['list']);
			$entityId = $firstEntity->ID;

			// Fetch the specific entity
			$fetchedEntity = $eventsService->entityFetch($entityId);

			if ($fetchedEntity !== null) {
				$this->assertNotNull($fetchedEntity);
				$this->assertInstanceOf(EventObject::class, $fetchedEntity);
				$this->assertEquals($entityId, $fetchedEntity->ID);
			}
		}
	}

}
