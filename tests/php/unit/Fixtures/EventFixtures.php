<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Fixtures;

use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Service\Local\LocalEventsService;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * Event test cases stored as iCalendar files in tests/php/resources/events
 */
final class EventFixtures extends ObjectFixtures {

	protected static function directory(): string {
		return __DIR__ . '/../../resources/events';
	}

	protected static function extension(): string {
		return 'ics';
	}

	protected static function ignored(): array {
		return ['Origin', 'ID', 'CID', 'Signature', 'CCID', 'CEID', 'CESN', 'CreatedOn', 'ModifiedOn'];
	}

	public static function calendar(string $name): VCalendar {
		return Reader::read(self::source($name));
	}

	/**
	 * @param list<string> $warnings receives the warnings raised during conversion
	 */
	public static function event(string $name, array &$warnings): EventObject {
		return self::capture(static fn () => (new LocalEventsService())->toEventObject(self::calendar($name)), $warnings);
	}
}
