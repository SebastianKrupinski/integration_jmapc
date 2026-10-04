<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Local;

use OCA\JMAPC\Objects\Event\EventAvailabilityTypes;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\Event\EventParticipantObject;
use OCA\JMAPC\Objects\Event\EventTagCollection;
use OCA\JMAPC\Service\Local\LocalEventsService;
use OCA\JMAPC\Tests\Unit\TestCase;
use Sabre\VObject\Reader;

class LocalEventsServiceTest extends TestCase {

	private LocalEventsService $eventsService;

	public function setUp(): void {
		parent::setUp();
		$this->eventsService = new LocalEventsService();
	}

	public function testToEventObjectTagsFromEveryCategoriesProperty(): void {
		$event = $this->eventsService->toEventObject(Reader::read(
			"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:tags\r\nDTSTART:20261121T100000Z\r\n"
			. "CATEGORIES:Favorites\r\nCATEGORIES:Meeting\r\nCATEGORIES:Work,Q4\\, Planning, \r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
		));

		$this->assertSame(['Favorites', 'Meeting', 'Work', 'Q4, Planning'], iterator_to_array($event->Tags));
	}

	public function testFromEventObjectTags(): void {
		$event = new EventObject();
		$event->UUID = 'tags';
		$event->StartsOn = new \DateTimeImmutable('2026-11-21T10:00:00Z');
		$event->Tags = new EventTagCollection(['Favorites', '', 'Q4, Planning']);

		$vEvent = $this->eventsService->fromEventObject($event)->VEVENT;

		$this->assertCount(1, $vEvent->select('CATEGORIES'));
		$this->assertSame(['Favorites', 'Q4, Planning'], $vEvent->CATEGORIES->getParts());
	}

	public function testFromEventObjectNoTags(): void {
		$event = new EventObject();
		$event->UUID = 'tags';
		$event->StartsOn = new \DateTimeImmutable('2026-11-21T10:00:00Z');

		$vEvent = $this->eventsService->fromEventObject($event)->VEVENT;

		$this->assertFalse(isset($vEvent->CATEGORIES));
	}

	public function testFromEventObjectDuration(): void {
		$event = new EventObject();
		$event->UUID = 'duration';
		$event->StartsOn = new \DateTimeImmutable('2026-11-13T15:00:00Z');
		$event->Duration = new \DateInterval('P1DT2H45M');

		$vEvent = $this->eventsService->fromEventObject($event)->VEVENT;

		$this->assertSame('P1DT2H45M', (string)$vEvent->DURATION);
		$this->assertFalse(isset($vEvent->DTEND));
	}

	public function testToEventObjectSequenceDefault(): void {
		$event = $this->eventsService->toEventObject(Reader::read(
			"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:sequence\r\nDTSTART:20261121T100000Z\r\nEND:VEVENT\r\n"
			. "BEGIN:VEVENT\r\nUID:sequence\r\nRECURRENCE-ID:20261128T100000Z\r\nDTSTART:20261128T110000Z\r\nSEQUENCE:3\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
		));

		$this->assertSame(0, $event->Sequence);
		$this->assertSame(3, $event->OccurrenceMutations['2026-11-28T10:00:00']->Sequence);
	}

	public function testToEventObjectIntervalDefault(): void {
		$event = $this->eventsService->toEventObject(Reader::read(
			"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:interval\r\nDTSTART:20261102T083000Z\r\n"
			. "RRULE:FREQ=DAILY;COUNT=10\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
		));

		$this->assertSame(1, $event->OccurrencePattern->Interval);
	}

	public function testFromEventObjectAttendeeAddress(): void {
		$event = new EventObject();
		$event->UUID = 'participants';
		$event->StartsOn = new \DateTimeImmutable('2026-11-17T10:00:00Z');
		$participant = new EventParticipantObject();
		$participant->Id = 'att-1';
		$participant->Address = 'bob@example.com';
		$event->Participants['att-1'] = $participant;

		$vEvent = $this->eventsService->fromEventObject($event)->VEVENT;

		$this->assertSame('mailto:bob@example.com', (string)$vEvent->ATTENDEE);
	}

	public function testFromEventObjectWithoutStart(): void {
		$event = new EventObject();
		$event->UUID = 'no-start';
		$event->Label = 'No Start';

		$vEvent = $this->eventsService->fromEventObject($event)->VEVENT;

		$this->assertFalse(isset($vEvent->DTSTART));
		$this->assertSame('No Start', (string)$vEvent->SUMMARY);
	}

	public function testToEventObjectUidFromEvent(): void {
		$event = $this->eventsService->toEventObject(Reader::read(
			"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nUID:calendar-uid\r\nBEGIN:VEVENT\r\nUID:event-uid\r\nDTSTART:20261110T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
		));

		$this->assertSame('event-uid', $event->UUID);
	}

	public function testAllDayEvent(): void {
		$event = $this->eventsService->toEventObject(Reader::read(
			"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:all-day\r\n"
			. "DTSTART;VALUE=DATE:20261116\r\nDTEND;VALUE=DATE:20261117\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
		));
		$this->assertTrue($event->Timeless);

		$vEvent = $this->eventsService->fromEventObject($event)->VEVENT;
		$this->assertSame('DATE', (string)$vEvent->DTSTART['VALUE']);
		$this->assertSame('20261116', (string)$vEvent->DTSTART);
		$this->assertFalse(isset($vEvent->DTSTART['TZID']));
		$this->assertSame('20261117', (string)$vEvent->DTEND);
	}

	public function testTimedEventIsNotAllDay(): void {
		$event = $this->eventsService->toEventObject(Reader::read(
			"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:timed\r\nDTSTART:20261116T000000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
		));

		$this->assertFalse($event->Timeless);
	}

	public function testToEventObjectTransparentIsFree(): void {
		$event = $this->eventsService->toEventObject(Reader::read(
			"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:free\r\nDTSTART:20261118T130000Z\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\n"
			. "BEGIN:VEVENT\r\nUID:busy\r\nRECURRENCE-ID:20261125T130000Z\r\nDTSTART:20261125T130000Z\r\nTRANSP:OPAQUE\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
		));

		$this->assertSame(EventAvailabilityTypes::Free, $event->Availability);
		$this->assertSame(EventAvailabilityTypes::Busy, $event->OccurrenceMutations['2026-11-25T13:00:00']->Availability);
	}
}
