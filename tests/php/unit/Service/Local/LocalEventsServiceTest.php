<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Local;

use OCA\JMAPC\Objects\Event\EventObject;
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
}
