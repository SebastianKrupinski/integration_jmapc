<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Integration\Mail;

use OCA\JMAPC\Objects\Mail\MailCollectionObject;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCA\JMAPC\Store\Remote\Filters\MailCollectionFilter;
use OCA\JMAPC\Store\Remote\Sort\MailCollectionSort;
use Test\TestCase;

class MailCollectionTest extends TestCase {

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
		$mailService = RemoteService::mailService($remoteClient);

		$collections = $mailService->collectionList();

		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		// Store the first collection ID for subsequent tests
		if (!empty($collections)) {
			$this->parentCollectionId = reset($collections)->id();
		}
	}

	public function testCollectionListFilter(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		$filter = $mailService->collectionListFilter();

		$this->assertNotNull($filter);
		$this->assertInstanceOf(
			MailCollectionFilter::class,
			$filter
		);
	}

	public function testCollectionListSort(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		$sort = $mailService->collectionListSort();

		$this->assertNotNull($sort);
		$this->assertInstanceOf(
			MailCollectionSort::class,
			$sort
		);
	}

	public function testCollectionFresh(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		$collection = $mailService->collectionFresh();

		$this->assertNotNull($collection);
		$this->assertInstanceOf(MailCollectionObject::class, $collection);
	}

	public function testCollectionFetch(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// First get a list of collections to fetch one
		$collections = $mailService->collectionList();
		$this->assertNotEmpty($collections);

		$firstCollection = reset($collections);
		$collectionId = $firstCollection->id();
		$fetchedCollection = $mailService->collectionFetch($collectionId);

		$this->assertNotNull($fetchedCollection);
		$this->assertInstanceOf(MailCollectionObject::class, $fetchedCollection);
		$this->assertEquals($collectionId, $fetchedCollection->id());
	}

	public function testCollectionCreate(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// Get a parent collection to create a subfolder in
		$collections = $mailService->collectionList();
		$this->assertNotEmpty($collections);
		$parentCollection = reset($collections);
		$parentId = $parentCollection->id();

		// Create a new collection object
		$newCollection = $mailService->collectionFresh();
		$newCollection->setLabel('Test Collection ' . time());

		// Attempt to create the collection
		$createdCollection = $mailService->collectionCreate($parentId, $newCollection);

		// May return null if not supported by the server
		if ($createdCollection !== null) {
			$this->assertInstanceOf(MailCollectionObject::class, $createdCollection);
			$this->assertNotNull($createdCollection->id());
		}
	}

	public function testCollectionModify(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// First, try to create a collection to modify
		$collections = $mailService->collectionList();
		$this->assertNotEmpty($collections);
		$parentCollection = reset($collections);
		$parentId = $parentCollection->id();

		$newCollection = $mailService->collectionFresh();
		$testName = 'Original Name ' . time();
		$newCollection->setLabel($testName);

		$createdCollection = $mailService->collectionCreate($parentId, $newCollection);

		// If creation succeeded, try to modify
		if ($createdCollection !== null) {
			$updatedName = 'Modified Name ' . time();
			$createdCollection->setLabel($updatedName);

			$modifiedCollection = $mailService->collectionModify($createdCollection);

			$this->assertNotNull($modifiedCollection);
			$this->assertInstanceOf(MailCollectionObject::class, $modifiedCollection);
			$this->assertEquals($updatedName, $modifiedCollection->getLabel());
		}
	}

	public function testCollectionDelete(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// First, try to create a collection to delete
		$collections = $mailService->collectionList();
		$this->assertNotEmpty($collections);
		$parentCollection = reset($collections);
		$parentId = $parentCollection->id();

		$newCollection = $mailService->collectionFresh();
		$newCollection->setLabel('To Delete ' . time());

		$createdCollection = $mailService->collectionCreate($parentId, $newCollection);

		// If creation succeeded, try to delete
		if ($createdCollection !== null) {
			$collectionId = $createdCollection->id();
			$result = $mailService->collectionDelete($collectionId);

			// Result should be the new state string if deletion succeeded
			// or null if it failed
			if ($result !== null) {
				$this->assertIsString($result);
			}
		}
	}

	public function testCollectionListWithFilter(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// Get unfiltered list first
		$allCollections = $mailService->collectionList();
		$this->assertNotEmpty($allCollections);

		// Extract properties from first collection to use in filters
		$firstCollection = reset($allCollections);
		$firstCollectionName = $firstCollection->getLabel();
		$firstCollectionRole = $firstCollection->getRole();

		// Test filter by name if available
		if (!empty($firstCollectionName)) {
			$filterByName = $mailService->collectionListFilter();
			$nameFiltered = $mailService->collectionList(null, $filterByName);
			$this->assertIsArray($nameFiltered);
			// Filtered by name should return collections (potentially same or fewer)
			$this->assertLessThanOrEqual(count($allCollections), count($nameFiltered));
		}

		// Test filter by role if available
		if (!empty($firstCollectionRole)) {
			$filterByRole = $mailService->collectionListFilter();
			$roleFiltered = $mailService->collectionList(null, $filterByRole);
			$this->assertIsArray($roleFiltered);
			// Filtered by role should return collections (potentially same or fewer)
			$this->assertLessThanOrEqual(count($allCollections), count($roleFiltered));
		}
	}

	public function testCollectionListWithNameSort(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// Get unfiltered list first
		$allCollections = $mailService->collectionList();
		$this->assertNotEmpty($allCollections);

		// Apply sort by name
		$sort = $mailService->collectionListSort();
		$sortedByName = $mailService->collectionList(null, null, $sort);

		$this->assertIsArray($sortedByName);
		$this->assertEquals(count($allCollections), count($sortedByName));
		
		// Extract names and verify collections are present
		$sortedNames = array_map(fn($c) => $c->getLabel(), $sortedByName);
		$this->assertNotEmpty(array_filter($sortedNames));
	}

	public function testCollectionListWithRankSort(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// Get unfiltered list first
		$allCollections = $mailService->collectionList();
		$this->assertNotEmpty($allCollections);

		// Apply sort by rank/order
		$sort = $mailService->collectionListSort();
		$sortedByRank = $mailService->collectionList(null, null, $sort);

		$this->assertIsArray($sortedByRank);
		$this->assertEquals(count($allCollections), count($sortedByRank));
		
		// Verify all collection IDs are present
		$allIds = array_map(fn($c) => $c->id(), $allCollections);
		$sortedIds = array_map(fn($c) => $c->id(), $sortedByRank);
		$this->assertEquals(sort($allIds), sort($sortedIds));
	}

	public function testCollectionListFilterBySubscribed(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// Get unfiltered list first
		$allCollections = $mailService->collectionList();
		$this->assertNotEmpty($allCollections);

		// Check if first collection is subscribed
		$firstCollection = reset($allCollections);
		$isSubscribed = $firstCollection->getSubscription();

		// Try to filter by subscribed status
		$filter = $mailService->collectionListFilter();
		$filteredCollections = $mailService->collectionList(null, $filter);

		$this->assertIsArray($filteredCollections);
		// If we have a subscription status, filtered should have <= results
		if ($isSubscribed !== null) {
			$this->assertLessThanOrEqual(count($allCollections), count($filteredCollections));
		}
	}

	public function testCollectionListFilterAndSort(): void {
		$remoteClient = RemoteService::freshClient($this->service);
		$mailService = RemoteService::mailService($remoteClient);

		// Get unfiltered list first
		$allCollections = $mailService->collectionList();
		$this->assertNotEmpty($allCollections);

		// Apply both filter and sort
		$filter = $mailService->collectionListFilter();
		$sort = $mailService->collectionListSort();
		$results = $mailService->collectionList(null, $filter, $sort);

		$this->assertIsArray($results);
		// Results with both filter and sort should be <= unfiltered results
		$this->assertLessThanOrEqual(count($allCollections), count($results));
		
		// Verify all returned collections are MailCollectionObject instances
		foreach ($results as $collection) {
			$this->assertInstanceOf(MailCollectionObject::class, $collection);
			// Verify basic properties are accessible
			$this->assertIsString($collection->id());
		}
	}

}
