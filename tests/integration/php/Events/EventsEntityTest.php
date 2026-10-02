<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Integration\Events;

use DateTime;
use DateTimeZone;
use JmapClient\Client;
use OCA\JMAPC\Objects\DeltaObject;
use OCA\JMAPC\Objects\Event\EventCollectionObject;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\Event\EventOccurrenceObject;
use OCA\JMAPC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\JMAPC\Objects\Event\EventParticipantObject;
use OCA\JMAPC\Objects\Event\EventParticipantStatusTypes;
use OCA\JMAPC\Objects\Event\EventParticipantTypes;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Tests\Integration\TestClientFactory;
use OCA\JMAPC\Tests\Unit\TestCase;
use Symfony\Component\Uid\UuidV4;

class EventsEntityTest extends TestCase {

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
			if (str_starts_with($collection->Label, 'Test Event Collection ')) {
				$this->eventsService->collectionDelete($collection->Id);
			}
		}
	}

	public function testEntityCreate(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a event for this test
		$event = $this->generateEvent('singleton_part_day_no_participants');
		$createdEvent = $this->eventsService->entityCreate($collectionId, $event);
		$this->assertNotNull($createdEvent);
		$this->assertInstanceOf(EventObject::class, $createdEvent);
		$this->assertNotEmpty($createdEvent->ID);
	}

	public function testEntityUpdate(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a event for this test
		$event = $this->generateEvent('singleton_part_day_no_participants');
		$createdEvent = $this->eventsService->entityCreate($collectionId, $event);
		$this->assertNotEmpty($createdEvent->ID);

		// Update the created event
		$createdEvent->Label = 'Updated Event Label';
		$updatedEvent = $this->eventsService->entityModify($collectionId, $createdEvent->ID, $createdEvent);
		$this->assertNotNull($updatedEvent);
		$this->assertInstanceOf(EventObject::class, $updatedEvent);
		$this->assertEquals('Updated Event Label', $updatedEvent->Label);
	}

	public function testEntityDelete(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a event for this test
		$event = $this->generateEvent('singleton_part_day_no_participants');
		$createdEvent = $this->eventsService->entityCreate($collectionId, $event);
		$this->assertNotEmpty($createdEvent->ID);

		// Delete the created event
		$deleteResult = $this->eventsService->entityDelete($collectionId,$createdEvent->ID);
		$this->assertNotEmpty($deleteResult);
		$this->assertEquals($createdEvent->ID, $deleteResult);
	}

	public function testEntityFetch(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create a event for this test
		$event = $this->generateEvent('singleton_part_day_no_participants');
		$createdEvent = $this->eventsService->entityCreate($collectionId, $event);
		$this->assertNotEmpty($createdEvent->ID);

		// Fetch the created event
		$fetchedEvent = $this->eventsService->entityFetch($collectionId, $createdEvent->ID);
		$this->assertNotNull($fetchedEvent);
		$this->assertInstanceOf(EventObject::class, $fetchedEvent);
		$this->assertEquals($createdEvent->ID, $fetchedEvent->ID);
	}

	public function testEntityListEmpty(): void {
		// Create a collection
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);
		
		// Get unfiltered list (should be empty)
		$allEntities = $this->eventsService->entityList($collectionId);
		$this->assertIsArray($allEntities);
		$this->assertArrayHasKey('list', $allEntities);
		$this->assertArrayHasKey('state', $allEntities);
		$this->assertEmpty($allEntities['list']);
	}

	public function testEntityListWithEvents(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create events for this test
		$eventKeys = [
			'singleton_part_day_no_participants',
			'singleton_full_day_no_participants',
		];
		foreach ($eventKeys as $key) {
			$event = $this->generateEvent($key);
			$createdEvent = $this->eventsService->entityCreate($collectionId, $event);
			$this->assertNotEmpty($createdEvent->ID);
		}

		// Get unfiltered list
		$allEntities = $this->eventsService->entityList($collectionId);
		$this->assertIsArray($allEntities);
		$this->assertArrayHasKey('list', $allEntities);
		$this->assertArrayHasKey('state', $allEntities);
		$this->assertCount(count($eventKeys), $allEntities['list']);
	}

	public function testEntityListWithFilter(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create events for this test
		$event1 = $this->generateEvent('singleton_part_day_no_participants');
		$event1->Label = 'Team Meeting Alpha';
		$createdEvent1 = $this->eventsService->entityCreate($collectionId, $event1);
		$this->assertNotEmpty($createdEvent1->ID);

		$event2 = $this->generateEvent('singleton_part_day_no_participants');
		$event2->Label = 'Project Review Beta';
		$createdEvent2 = $this->eventsService->entityCreate($collectionId, $event2);
		$this->assertNotEmpty($createdEvent2->ID);

		$event3 = $this->generateEvent('singleton_part_day_no_participants');
		$event3->Label = 'Client Presentation Gamma';
		$createdEvent3 = $this->eventsService->entityCreate($collectionId, $event3);
		$this->assertNotEmpty($createdEvent3->ID);

		// retrieve unfiltered list
		$allEntities = $this->eventsService->entityList($collectionId);
		$this->assertCount(3, $allEntities['list']);

		// construct filter condition
		$filter = $this->eventsService->entityListFilter();
		$filter->condition('text', 'Meeting');

		// retrieve filtered list
		$filteredEntities = $this->eventsService->entityList($collectionId, null, null, $filter);
		$this->assertIsArray($filteredEntities);
		$this->assertArrayHasKey('list', $filteredEntities);
		$this->assertCount(1, $filteredEntities['list']);
		
		// verify the filtered event
		$filteredEvent = reset($filteredEntities['list']);
		$this->assertInstanceOf(EventObject::class, $filteredEvent);
		$this->assertStringContainsString('Meeting', $filteredEvent->Label);
	}

	public function testEntityListWithSort(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Create events for this test
		$baseTime = new DateTime('2025-12-01 10:00:00', new DateTimeZone('UTC'));

		$event1 = $this->generateEvent('singleton_part_day_no_participants');
		$event1->Label = 'Event Charlie';
		$event1->StartsOn = (clone $baseTime)->modify('+2 days');
		$event1->EndsOn = (clone $baseTime)->modify('+2 days +1 hour');
		$createdEvent1 = $this->eventsService->entityCreate($collectionId, $event1);
		$this->assertNotEmpty($createdEvent1->ID);

		$event2 = $this->generateEvent('singleton_part_day_no_participants');
		$event2->Label = 'Event Alice';
		$event2->StartsOn = clone $baseTime;
		$event2->EndsOn = (clone $baseTime)->modify('+1 hour');
		$createdEvent2 = $this->eventsService->entityCreate($collectionId, $event2);
		$this->assertNotEmpty($createdEvent2->ID);

		$event3 = $this->generateEvent('singleton_part_day_no_participants');
		$event3->Label = 'Event Bob';
		$event3->StartsOn = (clone $baseTime)->modify('+1 day');
		$event3->EndsOn = (clone $baseTime)->modify('+1 day +1 hour');
		$createdEvent3 = $this->eventsService->entityCreate($collectionId, $event3);
		$this->assertNotEmpty($createdEvent3->ID);

		// construct ascending sort condition
		$sortAsc = $this->eventsService->entityListSort();
		$sortAsc->condition('start', true);

		// retrieve sorted list
		$sortedAscEntities = $this->eventsService->entityList($collectionId, null, null, null, $sortAsc);
		$this->assertIsArray($sortedAscEntities);
		$this->assertArrayHasKey('list', $sortedAscEntities);
		$this->assertCount(3, $sortedAscEntities['list']);

		// verify ascending order
		$eventsAsc = array_values($sortedAscEntities['list']);
		$this->assertEquals('Event Alice', $eventsAsc[0]->Label);
		$this->assertEquals('Event Bob', $eventsAsc[1]->Label);
		$this->assertEquals('Event Charlie', $eventsAsc[2]->Label);

		// construct descending sort condition
		$sortDesc = $this->eventsService->entityListSort();
		$sortDesc->condition('start', false);

		// retrieve sorted list
		$sortedDescEntities = $this->eventsService->entityList($collectionId, null, null, null, $sortDesc);
		$this->assertIsArray($sortedDescEntities);
		$this->assertArrayHasKey('list', $sortedDescEntities);
		$this->assertCount(3, $sortedDescEntities['list']);

		// verify descending order
		$eventsDesc = array_values($sortedDescEntities['list']);
		$this->assertEquals('Event Charlie', $eventsDesc[0]->Label);
		$this->assertEquals('Event Bob', $eventsDesc[1]->Label);
		$this->assertEquals('Event Alice', $eventsDesc[2]->Label);
	}

	public function testEntityDeltaEmpty(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Get initial state
		$initialList = $this->eventsService->entityDelta($collectionId, '');
		$this->assertNotNull($initialList);
		$this->assertInstanceOf(DeltaObject::class, $initialList);
		$this->assertNotEmpty($initialList->signature);

		// Get delta since initial state
		$delta = $this->eventsService->entityDelta($collectionId, $initialList->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
	}

	public function testEntityDeltaWithChanges(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);

		// Get initial state
		$delta = $this->eventsService->entityDelta($collectionId, '');
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);

		// Create a event for this test
		$event = $this->generateEvent('singleton_part_day_no_participants');
		$createdEvent = $this->eventsService->entityCreate($collectionId, $event);
		$this->assertNotEmpty($createdEvent->ID);
		// Get delta since initial state
		$delta = $this->eventsService->entityDelta($collectionId, $delta->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
		$this->assertCount(1, $delta->additions);

		// modify the created event
		$createdEvent->Label = 'Updated Event Label';
		$updatedEvent = $this->eventsService->entityModify($collectionId, $createdEvent->ID, $createdEvent);
		$this->assertNotNull($updatedEvent);
		// Get delta since last state
		$delta = $this->eventsService->entityDelta($collectionId, $delta->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
		$this->assertCount(1, $delta->additions);

		// delete the created event
		$this->eventsService->entityDelete($collectionId, $createdEvent->ID);
		// Get delta since last state
		$delta = $this->eventsService->entityDelta($collectionId, $delta->signature);
		$this->assertNotNull($delta);
		$this->assertInstanceOf(DeltaObject::class, $delta);
		$this->assertNotEmpty($delta->signature);
		$this->assertCount(1, $delta->deletions);
	}

	public function testEntityVariations(): void {
		// Create a collection for this test
		$collection = new EventCollectionObject();
		$collection->Label = 'Test Event Collection ' . time();
		$collectionId = $this->eventsService->collectionCreate($collection);
		$this->assertNotEmpty($collectionId);
		// Create all event variations for this test
		$eventKeys = [
			'singleton_part_day_no_participants',
			'singleton_full_day_no_participants',
			'singleton_part_day_with_participants',
			'singleton_full_day_with_participants',
			'recurring_part_day_daily',
			'recurring_part_day_weekly',
			'recurring_part_day_monthly_absolute',
			'recurring_part_day_monthly_relative',
			'recurring_part_day_yearly_absolute',
			'recurring_part_day_yearly_relative',
			'recurring_full_day_daily',
			'recurring_full_day_weekly',
			'recurring_full_day_monthly_absolute',
			'recurring_full_day_monthly_relative',
			'recurring_full_day_yearly_absolute',
			'recurring_full_day_yearly_relative',
		];
		foreach ($eventKeys as $key) {
			$event = $this->generateEvent($key);
			$createdEvent = $this->eventsService->entityCreate($collectionId, $event);
			$this->assertNotEmpty($createdEvent->ID);
		}
		// Get unfiltered list
		$entities = $this->eventsService->entityList($collectionId);
		$this->assertIsArray($entities);
		$this->assertArrayHasKey('list', $entities);
		$this->assertArrayHasKey('state', $entities);
		$this->assertCount(count($eventKeys), $entities['list']);
	}

	/**
	 * Generate a test event by key from the predefined collection
	 * 
	 * Available keys:
	 * - singleton_part_day_no_participants
	 * - singleton_full_day_no_participants
	 * - singleton_part_day_with_participants
	 * - singleton_full_day_with_participants
	 * - recurring_part_day_daily
	 * - recurring_part_day_weekly
	 * - recurring_part_day_monthly_absolute
	 * - recurring_part_day_monthly_relative
	 * - recurring_part_day_yearly_absolute
	 * - recurring_part_day_yearly_relative
	 * - recurring_full_day_daily
	 * - recurring_full_day_weekly
	 * - recurring_full_day_monthly_absolute
	 * - recurring_full_day_monthly_relative
	 * - recurring_full_day_yearly_absolute
	 * - recurring_full_day_yearly_relative
	 */
	private function generateEvent(string $key): EventObject {

		$eventData = $this->generateEventData($key);

		$event = new EventObject();
		$event->UUID = UuidV4::v4()->toRfc4122();
		$event->Label = $eventData['label'];
		$event->Description = $eventData['description'];
		$event->StartsOn = $eventData['startsOn'];
		$event->EndsOn = $eventData['endsOn'];
		$event->Timeless = $eventData['timeless'];
		
		// Add participants if needed
		if ($eventData['participants'] === 'default') {
			$participant1 = new EventParticipantObject();
			$participant1->Id = 'p1';
			$participant1->Name = 'John Doe';
			$participant1->Address = 'john@example.com';
			$participant1->Type = EventParticipantTypes::Individual;
			$participant1->Status = EventParticipantStatusTypes::Accepted;

			$participant2 = new EventParticipantObject();
			$participant2->Id = 'p2';
			$participant2->Name = 'Jane Smith';
			$participant2->Address = 'jane@example.com';
			$participant2->Type = EventParticipantTypes::Individual;
			$participant2->Status = EventParticipantStatusTypes::Tentative;

			$event->Participants['p1'] = $participant1;
			$event->Participants['p2'] = $participant2;
		}

		// Add recurrence pattern if needed
		if ($eventData['recurrence']) {
			$recurrence = $eventData['recurrence'];
			$occurrence = new EventOccurrenceObject();
			$occurrence->Precision = $recurrence['precision'];
			$occurrence->Interval = $recurrence['interval'];
			$occurrence->Iterations = $recurrence['iterations'];
			
			if (isset($recurrence['onDayOfWeek'])) {
				$occurrence->OnDayOfWeek = $recurrence['onDayOfWeek'];
			}
			if (isset($recurrence['onDayOfMonth'])) {
				$occurrence->OnDayOfMonth = $recurrence['onDayOfMonth'];
			}
			if (isset($recurrence['onPosition'])) {
				$occurrence->OnPosition = $recurrence['onPosition'];
			}
			if (isset($recurrence['onMonthOfYear'])) {
				$occurrence->OnMonthOfYear = $recurrence['onMonthOfYear'];
			}
			
			$event->OccurrencePattern = $occurrence;
		}

		return $event;
	}

	private function generateEventData(string $key): array {
		$baseTime = new DateTime('2025-11-15 10:00:00', new DateTimeZone('UTC'));
		
		return match($key) {
			'singleton_part_day_no_participants' => [
				'label' => 'Singleton Part Day Event',
				'description' => 'A single event during the day without participants',
				'startsOn' => clone $baseTime,
				'endsOn' => (clone $baseTime)->modify('+2 hours'),
				'timeless' => false,
				'participants' => null,
				'recurrence' => null,
			],
			'singleton_full_day_no_participants' => [
				'label' => 'Singleton Full Day Event',
				'description' => 'A single all-day event without participants',
				'startsOn' => new DateTime('2025-11-16 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-11-17 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => null,
				'recurrence' => null,
			],
			'singleton_part_day_with_participants' => [
				'label' => 'Meeting with Team',
				'description' => 'A meeting with multiple participants',
				'startsOn' => (clone $baseTime)->modify('+1 day'),
				'endsOn' => (clone $baseTime)->modify('+1 day +1 hour'),
				'timeless' => false,
				'participants' => 'default',
				'recurrence' => null,
			],
			'singleton_full_day_with_participants' => [
				'label' => 'Company Event',
				'description' => 'An all-day company event with participants',
				'startsOn' => new DateTime('2025-11-18 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-11-19 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => 'default',
				'recurrence' => null,
			],
			'recurring_part_day_daily' => [
				'label' => 'Daily Standup',
				'description' => 'Daily team standup meeting',
				'startsOn' => (clone $baseTime)->modify('+2 days'),
				'endsOn' => (clone $baseTime)->modify('+2 days +30 minutes'),
				'timeless' => false,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Daily,
					'interval' => 1,
					'iterations' => 10,
				],
			],
			'recurring_part_day_weekly' => [
				'label' => 'Weekly Review',
				'description' => 'Weekly team review meeting',
				'startsOn' => (clone $baseTime)->modify('+3 days'),
				'endsOn' => (clone $baseTime)->modify('+3 days +1 hour'),
				'timeless' => false,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Weekly,
					'interval' => 1,
					'iterations' => 8,
					'onDayOfWeek' => ['MO'],
				],
			],
			'recurring_part_day_monthly_absolute' => [
				'label' => 'Monthly Report',
				'description' => 'Monthly report meeting on the 15th',
				'startsOn' => new DateTime('2025-11-15 14:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-11-15 15:00:00', new DateTimeZone('UTC')),
				'timeless' => false,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Monthly,
					'interval' => 1,
					'iterations' => 6,
					'onDayOfMonth' => [15],
				],
			],
			'recurring_part_day_monthly_relative' => [
				'label' => 'Monthly Planning',
				'description' => 'Monthly planning on the 2nd Monday',
				'startsOn' => new DateTime('2025-11-10 10:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-11-10 11:00:00', new DateTimeZone('UTC')),
				'timeless' => false,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Monthly,
					'interval' => 1,
					'iterations' => 6,
					'onDayOfWeek' => ['MO'],
					'onPosition' => [2],
				],
			],
			'recurring_part_day_yearly_absolute' => [
				'label' => 'Annual Review',
				'description' => 'Annual review on June 10th',
				'startsOn' => new DateTime('2026-06-10 09:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2026-06-10 17:00:00', new DateTimeZone('UTC')),
				'timeless' => false,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Yearly,
					'interval' => 1,
					'iterations' => 3,
					'onMonthOfYear' => [6],
				],
			],
			'recurring_part_day_yearly_relative' => [
				'label' => 'Annual Conference',
				'description' => 'Annual conference on the 3rd Wednesday of May',
				'startsOn' => new DateTime('2026-05-20 10:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2026-05-20 16:00:00', new DateTimeZone('UTC')),
				'timeless' => false,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Yearly,
					'interval' => 1,
					'iterations' => 3,
					'onDayOfWeek' => ['WE'],
					'onPosition' => [3],
					'onMonthOfYear' => [5],
				],
			],
			'recurring_full_day_daily' => [
				'label' => 'Daily Task Block',
				'description' => 'Daily full-day task block',
				'startsOn' => new DateTime('2025-11-20 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-11-21 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Daily,
					'interval' => 1,
					'iterations' => 5,
				],
			],
			'recurring_full_day_weekly' => [
				'label' => 'Weekly Focus Day',
				'description' => 'Weekly full-day focus time',
				'startsOn' => new DateTime('2025-11-21 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-11-22 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Weekly,
					'interval' => 1,
					'iterations' => 8,
					'onDayOfWeek' => ['FR'],
				],
			],
			'recurring_full_day_monthly_absolute' => [
				'label' => 'Mid-Month Deadline',
				'description' => 'Monthly deadline on the 15th',
				'startsOn' => new DateTime('2025-12-15 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-12-16 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Monthly,
					'interval' => 1,
					'iterations' => 6,
					'onDayOfMonth' => [15],
				],
			],
			'recurring_full_day_monthly_relative' => [
				'label' => 'Monthly Training',
				'description' => 'Monthly training on the 2nd Monday',
				'startsOn' => new DateTime('2025-12-09 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2025-12-10 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Monthly,
					'interval' => 1,
					'iterations' => 6,
					'onDayOfWeek' => ['MO'],
					'onPosition' => [2],
				],
			],
			'recurring_full_day_yearly_absolute' => [
				'label' => 'Annual Holiday',
				'description' => 'Annual holiday on June 10th',
				'startsOn' => new DateTime('2026-06-10 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2026-06-11 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Yearly,
					'interval' => 1,
					'iterations' => 3,
					'onMonthOfYear' => [6],
				],
			],
			'recurring_full_day_yearly_relative' => [
				'label' => 'Annual Meeting',
				'description' => 'Annual meeting on the 3rd Wednesday of May',
				'startsOn' => new DateTime('2026-05-20 00:00:00', new DateTimeZone('UTC')),
				'endsOn' => new DateTime('2026-05-21 00:00:00', new DateTimeZone('UTC')),
				'timeless' => true,
				'participants' => null,
				'recurrence' => [
					'precision' => EventOccurrencePrecisionTypes::Yearly,
					'interval' => 1,
					'iterations' => 3,
					'onDayOfWeek' => ['WE'],
					'onPosition' => [3],
					'onMonthOfYear' => [5],
				],
			],
			default => throw new \InvalidArgumentException("Unknown test event key: $key"),
		};
	}
}
