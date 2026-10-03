<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Jmap\Contacts;

use DateTime;
use DateTimeZone;
use JmapClient\Client;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryObject;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryTypes;
use OCA\JMAPC\Objects\Contact\ContactCollectionObject;
use OCA\JMAPC\Objects\Contact\ContactEmailObject;
use OCA\JMAPC\Objects\Contact\ContactNoteObject;
use OCA\JMAPC\Objects\Contact\ContactObject;
use OCA\JMAPC\Objects\Contact\ContactOrganizationObject;
use OCA\JMAPC\Objects\Contact\ContactPhoneObject;
use OCA\JMAPC\Objects\Contact\ContactPhysicalLocationObject;
use OCA\JMAPC\Objects\Contact\ContactTitleObject;
use OCA\JMAPC\Objects\Contact\ContactTitleTypes;
use OCA\JMAPC\Objects\DeltaObject;
use OCA\JMAPC\Service\Remote\RemoteContactsService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Tests\Jmap\TestClientFactory;
use OCA\JMAPC\Tests\Unit\TestCase;
use Symfony\Component\Uid\UuidV4;

class ContactsEntityTest extends TestCase {

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
			if (str_starts_with($collection->Label, 'Test Contact Collection ')) {
				$this->contactsService->collectionDelete($collection->Id);
			}
		}
	}

	public function testEntityCreate(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a contact for this test
		$contact = $this->generateContact('basic');
		$createdContact = $this->contactsService->entityCreate($collectionId, $contact);
		$this->assertNotNull($createdContact);
		$this->assertInstanceOf(ContactObject::class, $createdContact);
		$this->assertNotEmpty($createdContact->ID);
	}

	public function testEntityUpdate(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a contact for this test
		$contact = $this->generateContact('basic');
		$createdContact = $this->contactsService->entityCreate($collectionId, $contact);
		$this->assertNotEmpty($createdContact->ID);

		// Update the created contact
		$createdContact->Label = 'Updated Contact Label';
		$updatedContact = $this->contactsService->entityModify($collectionId, $createdContact->ID, $createdContact);
		$this->assertNotNull($updatedContact);
		$this->assertInstanceOf(ContactObject::class, $updatedContact);
		$this->assertEquals('Updated Contact Label', $updatedContact->Label);
	}

	public function testEntityDelete(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a contact for this test
		$contact = $this->generateContact('basic');
		$createdContact = $this->contactsService->entityCreate($collectionId, $contact);
		$this->assertNotEmpty($createdContact->ID);

		// Delete the created contact
		$deleteResult = $this->contactsService->entityDelete($collectionId, $createdContact->ID);
		$this->assertNotEmpty($deleteResult);
		$this->assertEquals($createdContact->ID, $deleteResult);
	}

	public function testEntityFetch(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a contact for this test
		$contact = $this->generateContact('basic');
		$createdContact = $this->contactsService->entityCreate($collectionId, $contact);
		$this->assertNotEmpty($createdContact->ID);

		// Fetch the created contact
		$fetchedContact = $this->contactsService->entityFetch($collectionId, $createdContact->ID);
		$this->assertNotNull($fetchedContact);
		$this->assertInstanceOf(ContactObject::class, $fetchedContact);
		$this->assertEquals($createdContact->ID, $fetchedContact->ID);
	}

	public function testEntityListEmpty(): void {
		// Create a collection
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Get unfiltered list (should be empty)
		$allEntities = $this->contactsService->entityList($collectionId);
		$this->assertIsArray($allEntities);
		$this->assertArrayHasKey('list', $allEntities);
		$this->assertArrayHasKey('state', $allEntities);
		$this->assertEmpty($allEntities['list']);
	}

	public function testEntityListWithContacts(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create contacts for this test
		$contactKeys = [
			'basic',
			'with_email_phone',
		];
		foreach ($contactKeys as $key) {
			$contact = $this->generateContact($key);
			$createdContact = $this->contactsService->entityCreate($collectionId, $contact);
			$this->assertNotEmpty($createdContact->ID);
		}

		// Get unfiltered list
		$allEntities = $this->contactsService->entityList($collectionId);
		$this->assertIsArray($allEntities);
		$this->assertArrayHasKey('list', $allEntities);
		$this->assertArrayHasKey('state', $allEntities);
		$this->assertCount(count($contactKeys), $allEntities['list']);
	}

	public function testEntityListWithFilter(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create contacts for this test
		$contact1 = $this->generateContact('basic');
		$contact1->Name->First = 'Alice';
		$contact1->Name->Last = 'Anderson';
		$contact1->Label = 'Alice Anderson';
		$createdContact1 = $this->contactsService->entityCreate($collectionId, $contact1);
		$this->assertNotEmpty($createdContact1->ID);

		$contact2 = $this->generateContact('basic');
		$contact2->Name->First = 'Bob';
		$contact2->Name->Last = 'Brown';
		$contact2->Label = 'Bob Brown';
		$createdContact2 = $this->contactsService->entityCreate($collectionId, $contact2);
		$this->assertNotEmpty($createdContact2->ID);

		$contact3 = $this->generateContact('basic');
		$contact3->Name->First = 'Charlie';
		$contact3->Name->Last = 'Clark';
		$contact3->Label = 'Charlie Clark';
		$createdContact3 = $this->contactsService->entityCreate($collectionId, $contact3);
		$this->assertNotEmpty($createdContact3->ID);

		// retrieve unfiltered list first
		$allEntities = $this->contactsService->entityList($collectionId);
		$this->assertCount(3, $allEntities['list']);

		// construct filter condition
		$filter = $this->contactsService->entityListFilter();
		$filter->condition('text', 'Alice');

		// retrieve filtered list
		$filteredEntities = $this->contactsService->entityList($collectionId, null, null, $filter);
		$this->assertIsArray($filteredEntities);
		$this->assertArrayHasKey('list', $filteredEntities);
		$this->assertCount(1, $filteredEntities['list']);

		// verify the filtered contact is correct
		$filteredContact = reset($filteredEntities['list']);
		$this->assertInstanceOf(ContactObject::class, $filteredContact);
		$this->assertEquals('Alice', $filteredContact->Name->First);
	}

	public function testEntityListWithSort(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create contacts for this test
		$contact1 = $this->generateContact('basic');
		$contact1->Name->First = 'Charlie';
		$contact1->Name->Last = 'Clark';
		$contact1->Label = 'Charlie Clark';
		$createdContact1 = $this->contactsService->entityCreate($collectionId, $contact1);
		$this->assertNotEmpty($createdContact1->ID);

		$contact2 = $this->generateContact('basic');
		$contact2->Name->First = 'Alice';
		$contact2->Name->Last = 'Anderson';
		$contact2->Label = 'Alice Anderson';
		$createdContact2 = $this->contactsService->entityCreate($collectionId, $contact2);
		$this->assertNotEmpty($createdContact2->ID);

		$contact3 = $this->generateContact('basic');
		$contact3->Name->First = 'Bob';
		$contact3->Name->Last = 'Brown';
		$contact3->Label = 'Bob Brown';
		$createdContact3 = $this->contactsService->entityCreate($collectionId, $contact3);
		$this->assertNotEmpty($createdContact3->ID);

		// construct ascending sort condition
		$sortAsc = $this->contactsService->entityListSort();
		$sortAsc->condition('nameGiven', true);

		// retrieve sorted list
		$sortedAscEntities = $this->contactsService->entityList($collectionId, null, null, null, $sortAsc);
		$this->assertIsArray($sortedAscEntities);
		$this->assertArrayHasKey('list', $sortedAscEntities);
		$this->assertCount(3, $sortedAscEntities['list']);

		// verify ascending order
		$contactsAsc = array_values($sortedAscEntities['list']);
		$this->assertEquals('Alice', $contactsAsc[0]->Name->First);
		$this->assertEquals('Bob', $contactsAsc[1]->Name->First);
		$this->assertEquals('Charlie', $contactsAsc[2]->Name->First);

		// construct descending sort condition
		$sortDesc = $this->contactsService->entityListSort();
		$sortDesc->condition('nameGiven', false);

		// retrieve sorted list
		$sortedDescEntities = $this->contactsService->entityList($collectionId, null, null, null, $sortDesc);
		$this->assertIsArray($sortedDescEntities);
		$this->assertArrayHasKey('list', $sortedDescEntities);
		$this->assertCount(3, $sortedDescEntities['list']);

		// verify descending order
		$contactsDesc = array_values($sortedDescEntities['list']);
		$this->assertEquals('Charlie', $contactsDesc[0]->Name->First);
		$this->assertEquals('Bob', $contactsDesc[1]->Name->First);
		$this->assertEquals('Alice', $contactsDesc[2]->Name->First);
	}

	public function testEntityDeltaEmpty(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Get initial state
		$initialList = $this->contactsService->entityDelta($collectionId, '');
		$this->assertNotNull($initialList);
		$this->assertInstanceOf(DeltaObject::class, $initialList);
		$this->assertNotEmpty($initialList->signature);

		// Get delta since initial state
		$delta = $this->contactsService->entityDelta($collectionId, $initialList->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
	}

	public function testEntityDeltaWithChanges(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Get initial state
		$delta = $this->contactsService->entityDelta($collectionId, '');
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);

		// Create a contact for this test
		$contact = $this->generateContact('basic');
		$createdContact = $this->contactsService->entityCreate($collectionId, $contact);
		$this->assertNotEmpty($createdContact->ID);
		// Get delta since initial state
		$delta = $this->contactsService->entityDelta($collectionId, $delta->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
		$this->assertCount(1, $delta->additions);

		// modify the created contact
		$createdContact->Label = 'Updated Contact Label';
		$updatedContact = $this->contactsService->entityModify($collectionId, $createdContact->ID, $createdContact);
		$this->assertNotNull($updatedContact);
		// Get delta since last state
		$delta = $this->contactsService->entityDelta($collectionId, $delta->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
		$this->assertCount(1, $delta->additions);

		// delete the created contact
		$this->contactsService->entityDelete($collectionId, $createdContact->ID);
		// Get delta since last state
		$delta = $this->contactsService->entityDelta($collectionId, $delta->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
		$this->assertCount(1, $delta->deletions);
	}

	public function testEntityVariations(): void {
		// Create a collection for this test
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);
		// Create all contact variations for this test
		$contactKeys = [
			'basic',
			'with_email_phone',
			'with_organization',
			'with_address',
			'with_anniversary',
			'with_notes',
			'complete',
		];
		foreach ($contactKeys as $key) {
			$contact = $this->generateContact($key);
			$createdContact = $this->contactsService->entityCreate($collectionId, $contact);
			$this->assertNotEmpty($createdContact->ID);
		}
		// Get unfiltered list
		$entities = $this->contactsService->entityList($collectionId);
		$this->assertIsArray($entities);
		$this->assertArrayHasKey('list', $entities);
		$this->assertArrayHasKey('state', $entities);
		$this->assertCount(count($contactKeys), $entities['list']);
	}

	/**
	 * Generate a test contact by key from the predefined collection
	 *
	 * Available keys:
	 * - basic
	 * - with_email_phone
	 * - with_organization
	 * - with_address
	 * - with_anniversary
	 * - with_notes
	 * - complete
	 */
	private function generateContact(string $key): ContactObject {

		$contactData = $this->generateContactData($key);

		$contact = new ContactObject();
		$contact->UUID = UuidV4::v4()->toRfc4122();
		$contact->Label = $contactData['label'];
		$contact->Name->First = $contactData['firstName'];
		$contact->Name->Last = $contactData['lastName'];

		// Add email if needed
		if (!empty($contactData['email'])) {
			foreach ($contactData['email'] as $index => $emailData) {
				$email = new ContactEmailObject();
				$email->Id = 'email' . ($index + 1);
				$email->Address = $emailData['address'];
				$email->Context = $emailData['context'] ?? null;
				$email->Priority = $emailData['priority'] ?? null;
				$contact->Email['email' . ($index + 1)] = $email;
			}
		}

		// Add phone if needed
		if (!empty($contactData['phone'])) {
			foreach ($contactData['phone'] as $index => $phoneData) {
				$phone = new ContactPhoneObject();
				$phone->Id = 'phone' . ($index + 1);
				$phone->Number = $phoneData['number'];
				$phone->Context = $phoneData['context'] ?? null;
				$phone->Priority = $phoneData['priority'] ?? null;
				$contact->Phone['phone' . ($index + 1)] = $phone;
			}
		}

		// Add organization if needed
		if (!empty($contactData['organization'])) {
			foreach ($contactData['organization'] as $index => $orgData) {
				$org = new ContactOrganizationObject();
				$org->Id = 'org' . ($index + 1);
				$org->Label = $orgData['name'];
				foreach ($orgData['units'] ?? [] as $unit) {
					$org->Units->append($unit);
				}
				$contact->Organizations['org' . ($index + 1)] = $org;
			}
		}

		// Add title if needed
		if (!empty($contactData['title'])) {
			foreach ($contactData['title'] as $index => $titleData) {
				$title = new ContactTitleObject();
				$title->Id = 'title' . ($index + 1);
				$title->Kind = $titleData['kind'] ?? ContactTitleTypes::Title;
				$title->Label = $titleData['label'];
				$contact->Titles['title' . ($index + 1)] = $title;
			}
		}

		// Add address if needed
		if (!empty($contactData['address'])) {
			foreach ($contactData['address'] as $index => $addressData) {
				$address = new ContactPhysicalLocationObject();
				$address->Id = 'addr' . ($index + 1);
				$address->Street = $addressData['street'] ?? null;
				$address->Locality = $addressData['locality'] ?? null;
				$address->Region = $addressData['region'] ?? null;
				$address->Code = $addressData['code'] ?? null;
				$address->Country = $addressData['country'] ?? null;
				$address->Label = $addressData['label'] ?? null;
				$address->Context = $addressData['context'] ?? null;
				$contact->PhysicalLocations['addr' . ($index + 1)] = $address;
			}
		}

		// Add anniversary if needed
		if (!empty($contactData['anniversary'])) {
			foreach ($contactData['anniversary'] as $index => $anniversaryData) {
				$anniversary = new ContactAnniversaryObject();
				$anniversary->Type = $anniversaryData['type'];
				$anniversary->When = $anniversaryData['when'];
				$contact->Anniversaries['anniv' . ($index + 1)] = $anniversary;
			}
		}

		// Add notes if needed
		if (!empty($contactData['notes'])) {
			foreach ($contactData['notes'] as $index => $noteData) {
				$note = new ContactNoteObject();
				$note->Id = 'note' . ($index + 1);
				$note->Content = $noteData['content'];
				$contact->Notes['note' . ($index + 1)] = $note;
			}
		}

		return $contact;
	}

	private function generateContactData(string $key): array {
		return match($key) {
			'basic' => [
				'label' => 'John Doe',
				'firstName' => 'John',
				'lastName' => 'Doe',
				'email' => [],
				'phone' => [],
				'organization' => [],
				'title' => [],
				'address' => [],
				'anniversary' => [],
				'notes' => [],
			],
			'with_email_phone' => [
				'label' => 'Jane Smith',
				'firstName' => 'Jane',
				'lastName' => 'Smith',
				'email' => [
					[
						'address' => 'jane.smith@example.com',
						'label' => 'Work',
						'context' => 'work',
						'priority' => 1,
					],
					[
						'address' => 'jane@personal.com',
						'label' => 'Personal',
						'context' => 'home',
						'priority' => 2,
					],
				],
				'phone' => [
					[
						'number' => '+1-555-0100',
						'label' => 'Mobile',
						'context' => 'mobile',
						'priority' => 1,
					],
					[
						'number' => '+1-555-0101',
						'label' => 'Work',
						'context' => 'work',
						'priority' => 2,
					],
				],
				'organization' => [],
				'title' => [],
				'address' => [],
				'anniversary' => [],
				'notes' => [],
			],
			'with_organization' => [
				'label' => 'Bob Johnson',
				'firstName' => 'Bob',
				'lastName' => 'Johnson',
				'email' => [
					[
						'address' => 'bob.johnson@company.com',
						'label' => 'Work',
						'context' => 'work',
					],
				],
				'phone' => [],
				'organization' => [
					[
						'name' => 'ACME Corporation',
						'units' => ['Engineering', 'Research'],
					],
				],
				'title' => [
					[
						'kind' => ContactTitleTypes::Title,
						'label' => 'Senior Software Engineer',
					],
				],
				'address' => [],
				'anniversary' => [],
				'notes' => [],
			],
			'with_address' => [
				'label' => 'Alice Williams',
				'firstName' => 'Alice',
				'lastName' => 'Williams',
				'email' => [
					[
						'address' => 'alice@example.com',
					],
				],
				'phone' => [],
				'organization' => [],
				'title' => [],
				'address' => [
					[
						'street' => '123 Main Street',
						'locality' => 'Springfield',
						'region' => 'IL',
						'code' => '62701',
						'country' => 'USA',
						'label' => 'Home',
						'context' => 'home',
					],
					[
						'street' => '456 Business Ave',
						'locality' => 'Chicago',
						'region' => 'IL',
						'code' => '60601',
						'country' => 'USA',
						'label' => 'Work',
						'context' => 'work',
					],
				],
				'anniversary' => [],
				'notes' => [],
			],
			'with_anniversary' => [
				'label' => 'Charlie Brown',
				'firstName' => 'Charlie',
				'lastName' => 'Brown',
				'email' => [],
				'phone' => [],
				'organization' => [],
				'title' => [],
				'address' => [],
				'anniversary' => [
					[
						'type' => ContactAnniversaryTypes::Birth,
						'when' => new DateTime('1985-05-15', new DateTimeZone('UTC')),
					],
					[
						'type' => ContactAnniversaryTypes::Nuptial,
						'when' => new DateTime('2010-08-20', new DateTimeZone('UTC')),
					],
				],
				'notes' => [],
			],
			'with_notes' => [
				'label' => 'Diana Prince',
				'firstName' => 'Diana',
				'lastName' => 'Prince',
				'email' => [
					[
						'address' => 'diana@example.com',
					],
				],
				'phone' => [],
				'organization' => [],
				'title' => [],
				'address' => [],
				'anniversary' => [],
				'notes' => [
					[
						'content' => 'Met at the conference in 2023',
					],
					[
						'content' => 'Interested in collaboration on AI projects',
					],
				],
			],
			'complete' => [
				'label' => 'Michael Anderson',
				'firstName' => 'Michael',
				'lastName' => 'Anderson',
				'email' => [
					[
						'address' => 'michael.anderson@company.com',
						'label' => 'Work',
						'context' => 'work',
						'priority' => 1,
					],
					[
						'address' => 'mike@personal.com',
						'label' => 'Personal',
						'context' => 'home',
						'priority' => 2,
					],
				],
				'phone' => [
					[
						'number' => '+1-555-0200',
						'label' => 'Mobile',
						'context' => 'mobile',
						'priority' => 1,
					],
					[
						'number' => '+1-555-0201',
						'label' => 'Work',
						'context' => 'work',
						'priority' => 2,
					],
				],
				'organization' => [
					[
						'name' => 'Global Tech Inc',
						'units' => ['Product Development', 'Innovation'],
					],
				],
				'title' => [
					[
						'kind' => ContactTitleTypes::Role,
						'label' => 'Chief Technology Officer',
					],
				],
				'address' => [
					[
						'street' => '789 Executive Blvd',
						'locality' => 'New York',
						'region' => 'NY',
						'code' => '10001',
						'country' => 'USA',
						'label' => 'Work',
						'context' => 'work',
					],
				],
				'anniversary' => [
					[
						'type' => ContactAnniversaryTypes::Birth,
						'when' => new DateTime('1980-03-10', new DateTimeZone('UTC')),
					],
				],
				'notes' => [
					[
						'content' => 'Key decision maker for technology partnerships',
					],
				],
			],
			default => throw new \InvalidArgumentException("Unknown test contact key: $key"),
		};
	}
}
