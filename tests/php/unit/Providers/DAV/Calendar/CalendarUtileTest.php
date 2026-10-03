<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Providers\DAV\Calendar;

use OCA\JMAPC\Providers\DAV\Calendar\CalendarUtile;
use OCA\JMAPC\Tests\Unit\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

class CalendarUtileTest extends TestCase {

	private function read(string ...$lines): VCalendar {
		return Reader::read(implode("\r\n", [
			'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//EN',
			'BEGIN:VEVENT', 'UID:test', 'DTSTAMP:20260101T000000Z', 'DTSTART:20260101T100000Z',
			...$lines,
			'END:VEVENT', 'END:VCALENDAR',
		]));
	}

	private function xid(Property $property): ?string {
		return ($property->parameters()['X-ID'] ?? null)?->getValue();
	}

	public function testAssignsMissingIds(): void {
		$vObject = $this->read(
			'ORGANIZER:mailto:organizer@example.com',
			'ATTENDEE:mailto:one@example.com',
			'ATTENDEE:mailto:two@example.com',
			'LOCATION:Room 4',
			'ATTACH:https://example.com/file.pdf',
		);

		CalendarUtile::normalizeProperties($vObject);

		$event = $vObject->VEVENT;
		$this->assertNotEmpty($this->xid($event->ORGANIZER));
		$this->assertNotEmpty($this->xid($event->LOCATION));
		$this->assertNotEmpty($this->xid($event->ATTACH));
		$attendees = $event->select('ATTENDEE');
		$this->assertNotEmpty($this->xid($attendees[0]));
		$this->assertNotSame($this->xid($attendees[0]), $this->xid($attendees[1]));
	}

	public function testKeepsExistingIds(): void {
		$vObject = $this->read(
			'ORGANIZER;X-ID=organizer-1:mailto:organizer@example.com',
			'ATTENDEE;X-ID=attendee-1:mailto:one@example.com',
			'LOCATION;X-ID=location-1:Room 4',
		);

		CalendarUtile::normalizeProperties($vObject);
		CalendarUtile::normalizeProperties($vObject);

		$event = $vObject->VEVENT;
		$this->assertSame(['organizer-1'], $event->ORGANIZER->parameters()['X-ID']->getParts());
		$this->assertSame(['attendee-1'], $event->ATTENDEE->parameters()['X-ID']->getParts());
		$this->assertSame(['location-1'], $event->LOCATION->parameters()['X-ID']->getParts());
	}

	public function testReplacesEmptyIds(): void {
		$vObject = $this->read('LOCATION;X-ID=:Room 4');

		CalendarUtile::normalizeProperties($vObject);

		$this->assertNotEmpty($this->xid($vObject->VEVENT->LOCATION));
		$this->assertCount(1, $vObject->VEVENT->LOCATION->parameters()['X-ID']->getParts());
	}

	public function testAssignsAlarmIds(): void {
		$vObject = $this->read(
			'BEGIN:VALARM', 'ACTION:DISPLAY', 'TRIGGER:-PT15M', 'END:VALARM',
			'BEGIN:VALARM', 'X-ID:', 'ACTION:DISPLAY', 'TRIGGER:-PT5M', 'END:VALARM',
			'BEGIN:VALARM', 'X-ID:alarm-1', 'ACTION:DISPLAY', 'TRIGGER:-PT1M', 'END:VALARM',
		);

		CalendarUtile::normalizeProperties($vObject);

		$alarms = $vObject->VEVENT->select('VALARM');
		$this->assertNotEmpty($alarms[0]->{'X-ID'}->getValue());
		$this->assertNotEmpty($alarms[1]->{'X-ID'}->getValue());
		$this->assertSame('alarm-1', $alarms[2]->{'X-ID'}->getValue());
	}

	public function testLowercasesAttendees(): void {
		$vObject = $this->read('ATTENDEE:mailto:One.Person@Example.com');

		CalendarUtile::normalizeProperties($vObject);

		$this->assertSame('mailto:one.person@example.com', $vObject->VEVENT->ATTENDEE->getValue());
	}
}
