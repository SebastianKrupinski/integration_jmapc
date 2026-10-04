<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Jmap\Events;

use JmapClient\Client;
use OCA\JMAPC\Objects\Event\EventCollectionObject;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Tests\Jmap\TestClientFactory;
use OCA\JMAPC\Tests\Unit\Fixtures\EventFixtures;
use OCA\JMAPC\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('DB')]
class EventsFixturesTest extends TestCase {

	private Client $remoteClient;
	private RemoteEventsService $eventsService;

	public static function fixtures(): array {
		return EventFixtures::provider();
	}

	public function setUp(): void {
		parent::setUp();
		TestClientFactory::checkRequirements($this);
		$this->remoteClient = TestClientFactory::InstanceClient();
		$this->eventsService = RemoteService::eventsService($this->remoteClient);
	}

	public function tearDown(): void {
		parent::tearDown();

		if (!isset($this->eventsService)) {
			return;
		}

		$collections = $this->eventsService->collectionList();

		foreach ($collections as $collection) {
			if (str_starts_with($collection->Label, 'Test Event Collection ')) {
				$this->eventsService->collectionDelete($collection->Id);
			}
		}
	}

	/**
	 * iCalendar -> event -> server -> event
	 */
	#[DataProvider('fixtures')]
	public function testServerRoundTrip(string $name): void {
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		// ids are strings and "0" is a valid id, so assertNotEmpty() does not fit
		$this->assertNotNull($collectionId);
		$this->assertNotSame('', $collectionId);

		$warnings = [];
		$expected = EventFixtures::event($name, $warnings);
		$warnings = [];

		$actual = EventFixtures::attempt(function () use ($collectionId, $expected) {
			$created = $this->eventsService->entityCreate($collectionId, $expected);
			$this->assertNotNull($created?->ID);
			$this->assertNotSame('', $created->ID);
			return $this->eventsService->entityFetch($collectionId, $created->ID);
		}, $warnings, $error);

		EventFixtures::assertEquivalent($name, 'server', $expected, $actual, $warnings, $error);
	}
}
