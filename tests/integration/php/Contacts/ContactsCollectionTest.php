<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Integration\Contacts;

use JmapClient\Client;
use OCA\JMAPC\Objects\Contact\ContactCollectionObject;
use OCA\JMAPC\Service\Remote\RemoteContactsService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Tests\Integration\TestClientFactory;
use OCA\JMAPC\Tests\Unit\TestCase;

class ContactsCollectionTest extends TestCase {

	private Client $remoteClient;
	private RemoteContactsService $contactsService;

	public function setUp(): void {
		parent::setUp();
		$this->remoteClient = TestClientFactory::InstanceClient();
		$this->contactsService = RemoteService::contactsService($this->remoteClient);
	}

	public function tearDown(): void {
		parent::tearDown();

		$collections = $this->contactsService->collectionList();
		
		foreach ($collections as $collection) {
			if (str_starts_with($collection->Label, 'Test Contact Collection ') ||
			    str_starts_with($collection->Label, 'Modified Contact Collection ') ||
			    str_starts_with($collection->Label, 'To Delete Contact Collection ')) {
				$this->contactsService->collectionDelete($collection->Id);
			}
		}
	}

	public function testCollectionCreate(): void {
		// create a collection
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);
		$this->assertIsString($collectionId);
	}

	public function testCollectionModify(): void {
		// Create a new collection object
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// modify the collection
		$collection->Label = 'Modified Contact Collection ' . time();
		$result = $this->contactsService->collectionModify($collectionId, $collection);
		$this->assertNotEmpty($result);
		$this->assertEquals($collectionId, $result);
	}

	public function testCollectionDelete(): void {
		// create a collection
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// delete the collection
		$result = $this->contactsService->collectionDelete($collectionId);
		$this->assertNotEmpty($result);
		$this->assertEquals($collectionId, $result);
	}

	public function testCollectionFetch(): void {
		// Create a new collection object
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// fetch the collection
		$fetchedCollection = $this->contactsService->collectionFetch($collectionId);
		$this->assertNotNull($fetchedCollection);
		$this->assertInstanceOf(ContactCollectionObject::class, $fetchedCollection);
		$this->assertEquals($collectionId, $fetchedCollection->Id);
	}

	public function testCollectionList(): void {
		$collections = $this->contactsService->collectionList();

		$this->assertNotEmpty($collections);
		$this->assertIsArray($collections);
		$this->assertInstanceOf(ContactCollectionObject::class, reset($collections));
	}

}
