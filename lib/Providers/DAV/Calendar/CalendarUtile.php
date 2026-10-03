<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\DAV\Calendar;

use Sabre\VObject\Component\VCalendar;

/**
 * Calendar (iCalendar) helpers
 */
final class CalendarUtile {
	/** properties that carry an X-ID parameter identifying the entry */
	private const IDENTIFIED_PROPERTIES = ['ORGANIZER', 'ATTENDEE', 'LOCATION', 'ATTACH'];

	public static function normalizeProperties(VCalendar $vObject): void {
		foreach ($vObject->getComponents() as $component) {
			if ($component->name === 'VTIMEZONE') {
				continue;
			}
			foreach (self::IDENTIFIED_PROPERTIES as $name) {
				foreach ($component->select($name) as $property) {
					if (empty(($property->parameters()['X-ID'] ?? null)?->getValue())) {
						$property->offsetUnset('X-ID');
						$property->add('X-ID', uniqid());
					}
				}
			}
			foreach ($component->select('ATTENDEE') as $property) {
				$property->setValue(mb_strtolower($property->getValue()));
			}
			// alarms are components, their X-ID is a property
			foreach ($component->select('VALARM') as $alarm) {
				if ($alarm->{'X-ID'} === null) {
					$alarm->add('X-ID', uniqid());
				} elseif (empty($alarm->{'X-ID'}->getValue())) {
					$alarm->{'X-ID'}->setValue(uniqid());
				}
			}
		}
	}
}
