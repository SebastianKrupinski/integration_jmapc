<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Jmap\Contacts;

use JmapClient\Client;
use OCA\JMAPC\Objects\Contact\ContactCollectionObject;
use OCA\JMAPC\Service\Remote\RemoteContactsService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Tests\Jmap\TestClientFactory;
use OCA\JMAPC\Tests\Unit\Fixtures\ContactFixtures;
use OCA\JMAPC\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('DB')]
class ContactsFixturesTest extends TestCase {

	private Client $remoteClient;
	private RemoteContactsService $contactsService;

	public static function fixtures(): array {
		return ContactFixtures::provider();
	}

	public function setUp(): void {
		parent::setUp();
		TestClientFactory::checkRequirements($this);
		$this->remoteClient = TestClientFactory::InstanceClient();
		$this->contactsService = RemoteService::contactsService($this->remoteClient);
	}

	public function tearDown(): void {
		parent::tearDown();

		if (!isset($this->contactsService)) {
			return;
		}

		$collections = $this->contactsService->collectionList();

		foreach ($collections as $collection) {
			if (str_starts_with($collection->Label, 'Test Contact Collection ')) {
				$this->contactsService->collectionDelete($collection->Id);
			}
		}
	}

	/**
	 * vCard -> contact -> server -> contact
	 */
	#[DataProvider('fixtures')]
	public function testServerRoundTrip(string $name): void {
		$collection = new ContactCollectionObject();
		$collection->Label = 'Test Contact Collection ' . time();
		$collectionId = $this->contactsService->collectionCreate($collection);
		// ids are strings and "0" is a valid id, so assertNotEmpty() does not fit
		$this->assertNotNull($collectionId);
		$this->assertNotSame('', $collectionId);

		$warnings = [];
		$expected = ContactFixtures::contact($name, $warnings);
		$warnings = [];

		$actual = ContactFixtures::capture(function () use ($collectionId, $expected) {
			$created = $this->contactsService->entityCreate($collectionId, $expected);
			$this->assertNotNull($created?->ID);
			$this->assertNotSame('', $created->ID);
			return $this->contactsService->entityFetch($collectionId, $created->ID);
		}, $warnings);
		$this->assertNotNull($actual);

		ContactFixtures::assertEquivalent($name, 'server', $expected, $actual, $warnings);
	}
}
