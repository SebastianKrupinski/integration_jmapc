<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use JmapClient\Responses\Calendar\EventParameters as EventParametersResponse;
use OCA\JMAPC\Objects\Event\EventMutationObject;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\Event\EventOccurrenceObject;
use OCA\JMAPC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\JMAPC\Objects\Event\EventParticipantObject;
use OCA\JMAPC\Objects\Event\EventParticipantRoleTypes;
use OCA\JMAPC\Objects\Event\EventParticipantStatusTypes;
use OCA\JMAPC\Objects\Event\EventParticipantTypes;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use OCA\JMAPC\Store\Remote\Filters\EventFilter;
use OCA\JMAPC\Store\Remote\Sort\EventSort;
use PHPUnit\Framework\TestCase;

class RemoteEventsServiceTest extends TestCase {

	private RemoteEventsService $eventsService;

	public function setUp(): void {
		parent::setUp();

		// Instantiate the RemoteEventsService
		$this->eventsService = new RemoteEventsService();
	}

	public function testEntityListFilter(): void {
		// instantiate the filter
		$filter = $this->eventsService->entityListFilter();
		$this->assertInstanceOf(EventFilter::class, $filter);

		// retrieve attributes
		$attributes = $filter->attributes();
		$this->assertIsArray($attributes);

		// Assert that the expected attributes are present
		$this->assertArrayHasKey('before', $attributes);
		$this->assertArrayHasKey('after', $attributes);
		$this->assertArrayHasKey('uid', $attributes);
		$this->assertArrayHasKey('text', $attributes);
		$this->assertArrayHasKey('title', $attributes);
		$this->assertArrayHasKey('description', $attributes);
		$this->assertArrayHasKey('location', $attributes);
		$this->assertArrayHasKey('owner', $attributes);
		$this->assertArrayHasKey('attendee', $attributes);
	}

	public function testEntityListSort(): void {
		// instantiate the sort
		$sort = $this->eventsService->entityListSort();
		$this->assertInstanceOf(EventSort::class, $sort);

		// retrieve attributes
		$attributes = $sort->attributes();
		$this->assertIsArray($attributes);

		// Assert that the expected attributes are present
		$this->assertArrayHasKey('created', $attributes);
		$this->assertArrayHasKey('modified', $attributes);
		$this->assertArrayHasKey('start', $attributes);
		$this->assertArrayHasKey('uid', $attributes);
		$this->assertArrayHasKey('recurrence', $attributes);
	}

	public function testFromEventObjectStartTimeZone(): void {
		$event = new EventObject();
		$event->StartsOn = new \DateTimeImmutable('2026-11-12T14:00:00', new \DateTimeZone('Europe/Berlin'));
		$event->StartsTZ = new \DateTimeZone('Europe/Berlin');
		$event->EndsOn = new \DateTimeImmutable('2026-11-12T15:30:00', new \DateTimeZone('Europe/Berlin'));

		$card = null;
		$this->eventsService->fromEventObject($event)->bind($card);

		$this->assertSame('Europe/Berlin', $card->timeZone);
		$this->assertSame('2026-11-12T14:00:00', $card->start);
		$this->assertSame('PT1H30M', $card->duration);
	}

	public function testFromEventObjectStartConvertedToEventTimeZone(): void {
		$event = new EventObject();
		$event->StartsOn = new \DateTimeImmutable('2026-11-12T13:00:00', new \DateTimeZone('UTC'));
		$event->TimeZone = new \DateTimeZone('Europe/Berlin');

		$card = null;
		$this->eventsService->fromEventObject($event)->bind($card);

		$this->assertSame('Europe/Berlin', $card->timeZone);
		$this->assertSame('2026-11-12T14:00:00', $card->start);
	}

	public function testToEventObjectStartTimeZone(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'timeZone' => 'Europe/Berlin',
			'start' => '2026-11-12T14:00:00',
			'duration' => 'PT1H30M',
		]));

		$this->assertSame('2026-11-12T14:00:00+01:00', $event->StartsOn->format(DATE_ATOM));
		$this->assertSame('Europe/Berlin', $event->StartsTZ->getName());
		$this->assertSame('2026-11-12T15:30:00+01:00', $event->EndsOn->format(DATE_ATOM));
		$this->assertSame('Europe/Berlin', $event->EndsTZ->getName());
		$this->assertNull($event->TimeZone);
	}

	public function testToEventObjectMutationWithoutTimeZone(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'start' => '2026-11-04T09:00:00',
			'duration' => 'PT30M',
			'recurrenceOverrides' => [
				'2026-11-11T09:00:00' => ['start' => '2026-11-11T13:00:00'],
			],
		]));

		$this->assertCount(1, $event->OccurrenceMutations);
		$this->assertNull($event->OccurrenceMutations['2026-11-11T09:00:00']->mutationTz);
	}

	public function testToEventObjectTags(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse(json_decode(
			'{"calendarIds":{"calendar-1":true},"keywords":{"Finance":true,"2026":true}}',
			true
		)));

		$this->assertSame(['Finance', '2026'], iterator_to_array($event->Tags));
	}

	public function testToEventObjectYearlyMonthOfYear(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'start' => '2027-06-10T00:00:00',
			'recurrenceRule' => ['@type' => 'RecurrenceRule', 'frequency' => 'yearly', 'byMonth' => [6], 'byMonthDay' => [10]],
		]));

		$this->assertSame([6], $event->OccurrencePattern->OnMonthOfYear);
		$this->assertSame([10], $event->OccurrencePattern->OnDayOfMonth);
	}

	public function testFromEventObjectParticipant(): void {
		$event = new EventObject();
		$participant = new EventParticipantObject();
		$participant->Id = 'att-1';
		$participant->Address = 'room101@example.com';
		$participant->Type = EventParticipantTypes::Location;
		$participant->Status = EventParticipantStatusTypes::Tentative;
		$participant->Roles[] = EventParticipantRoleTypes::Informational;
		$event->Participants['att-1'] = $participant;

		$card = null;
		$this->eventsService->fromEventObject($event)->bind($card);

		$this->assertSame('mailto:room101@example.com', $card->participants->{'att-1'}->calendarAddress);
		$this->assertSame('location', $card->participants->{'att-1'}->kind);
		$this->assertSame('tentative', $card->participants->{'att-1'}->participationStatus);
		$this->assertEquals((object)['informational' => true], $card->participants->{'att-1'}->roles);
	}

	public function testToEventObjectParticipant(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'participants' => [
				'att-1' => [
					'email' => 'room101@example.com',
					'kind' => 'location',
					'participationStatus' => 'tentative',
					'roles' => ['attendee' => true, 'x-unknown' => true, 'optional' => true],
				],
			],
		]));

		$participant = $event->Participants['att-1'];
		$this->assertSame(EventParticipantTypes::Location, $participant->Type);
		$this->assertSame(EventParticipantStatusTypes::Tentative, $participant->Status);
		$this->assertSame([EventParticipantRoleTypes::Attendee, EventParticipantRoleTypes::Optional], iterator_to_array($participant->Roles));
	}

	public function testToEventObjectNotificationId(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'alerts' => [
				'alarm-1' => ['@type' => 'Alert', 'action' => 'display', 'trigger' => ['@type' => 'OffsetTrigger', 'offset' => '-PT15M', 'relativeTo' => 'start']],
			],
		]));

		$this->assertSame('alarm-1', $event->Notifications['alarm-1']->Id);
	}

	public function testToEventObjectMutationTimeZone(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'timeZone' => 'Europe/Berlin',
			'start' => '2026-11-04T09:00:00',
			'recurrenceOverrides' => [
				'2026-11-11T09:00:00' => ['start' => '2026-11-11T13:00:00', 'recurrenceIdTimeZone' => 'America/New_York'],
			],
		]));

		$this->assertSame('America/New_York', $event->OccurrenceMutations['2026-11-11T09:00:00']->mutationTz);
	}

	public function testFromEventObjectExclusion(): void {
		$event = new EventObject();
		$event->StartsOn = new \DateTimeImmutable('2026-11-03T09:00:00Z');
		$exclusion = new EventMutationObject();
		$exclusion->mutationId = new \DateTimeImmutable('2026-11-10T09:00:00Z');
		$exclusion->mutationExclusion = true;
		$exclusion->Label = 'not sent';
		$event->OccurrenceMutations['2026-11-10T09:00:00'] = $exclusion;

		$card = null;
		$this->eventsService->fromEventObject($event)->bind($card);

		$override = $card->recurrenceOverrides->{'2026-11-10T09:00:00'};
		$this->assertTrue($override->excluded);
		$this->assertObjectNotHasProperty('title', $override);
	}

	public function testToEventObjectExclusion(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'start' => '2026-11-03T09:00:00',
			'recurrenceOverrides' => [
				'2026-11-10T09:00:00' => ['excluded' => true],
				'2026-11-17T09:00:00' => ['title' => 'Moved'],
			],
		]));

		$exclusion = $event->OccurrenceMutations['2026-11-10T09:00:00'];
		$this->assertTrue($exclusion->mutationExclusion);
		$this->assertNull($exclusion->Sequence);
		$this->assertNull($event->OccurrenceMutations['2026-11-17T09:00:00']->mutationExclusion);
		$this->assertSame('Moved', $event->OccurrenceMutations['2026-11-17T09:00:00']->Label);
	}

	public function testFromEventObjectDaysOfWeek(): void {
		$event = new EventObject();
		$event->StartsOn = new \DateTimeImmutable('2026-11-09T10:00:00Z');
		$rule = new EventOccurrenceObject();
		$rule->Precision = EventOccurrencePrecisionTypes::Monthly;
		$rule->OnDayOfWeek = ['MO', '2TU', '-1FR', 'XX'];
		$event->OccurrencePattern = $rule;

		$card = null;
		$this->eventsService->fromEventObject($event)->bind($card);

		$this->assertEquals([
			(object)['@type' => 'NDay', 'day' => 'mo'],
			(object)['@type' => 'NDay', 'day' => 'tu', 'nthOfPeriod' => 2],
			(object)['@type' => 'NDay', 'day' => 'fr', 'nthOfPeriod' => -1],
		], $card->recurrenceRule->byDay);
	}

	public function testToEventObjectDaysOfWeek(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'start' => '2026-11-02T16:00:00',
			'recurrenceRule' => [
				'@type' => 'RecurrenceRule',
				'frequency' => 'weekly',
				'byDay' => [['day' => 'mo'], ['day' => 'we', 'nthOfPeriod' => 2]],
			],
		]));

		$this->assertSame(['MO', '2WE'], $event->OccurrencePattern->OnDayOfWeek);
	}

	public function testToEventObjectMonthsOfYearAsStrings(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'start' => '2027-06-10T00:00:00',
			'recurrenceRule' => ['@type' => 'RecurrenceRule', 'frequency' => 'yearly', 'byMonth' => ['6', '5L', '12'], 'byMonthDay' => [10]],
		]));

		$this->assertSame([6, 12], $event->OccurrencePattern->OnMonthOfYear);
	}

	public function testToEventObjectMutationInheritsTimeZone(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'timeZone' => 'Europe/Berlin',
			'start' => '2026-11-04T09:00:00',
			'duration' => 'PT30M',
			'recurrenceOverrides' => [
				'2026-11-11T09:00:00' => ['start' => '2026-11-11T13:00:00', 'duration' => 'PT30M'],
			],
		]));

		$mutation = $event->OccurrenceMutations['2026-11-11T09:00:00'];
		$this->assertSame('2026-11-11T13:00:00+01:00', $mutation->StartsOn->format(DATE_ATOM));
		$this->assertSame('Europe/Berlin', $mutation->StartsTZ->getName());
		$this->assertSame('2026-11-11T13:30:00+01:00', $mutation->EndsOn->format(DATE_ATOM));
		$this->assertSame('Europe/Berlin', $mutation->EndsTZ->getName());
	}

	public function testToEventObjectParticipantCalendarAddress(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'participants' => [
				'att-1' => ['calendarAddress' => 'MAILTO:bob@example.com', 'email' => 'bob.contact@example.com'],
				'att-2' => ['email' => 'carol@example.com'],
			],
		]));

		$this->assertSame('bob@example.com', $event->Participants['att-1']->Address);
		$this->assertSame('carol@example.com', $event->Participants['att-2']->Address);
	}
}
