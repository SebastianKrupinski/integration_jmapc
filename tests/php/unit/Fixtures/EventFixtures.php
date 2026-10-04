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

	/** names of the UTC time zone, servers return any of them for UTC */
	private const UTC_ALIASES = ['UTC', 'Etc/UTC', 'Etc/UCT', 'Etc/Universal', 'Etc/Zulu', 'UCT', 'Universal', 'Zulu', 'Z'];

	protected static function directory(): string {
		return __DIR__ . '/../../resources/events';
	}

	protected static function extension(): string {
		return 'ics';
	}

	protected static function ignored(): array {
		return ['Origin', 'ID', 'CID', 'Signature', 'CCID', 'CEID', 'CESN', 'CreatedOn', 'ModifiedOn'];
	}

	/**
	 * Compares the end of the event and of each mutation as an end date time,
	 * JSCalendar only has start and duration so either form comes back as the other,
	 * and compares every name of the UTC time zone as UTC
	 */
	protected static function normalize(array $values): array {
		foreach ($values as $path => $value) {
			if (preg_match('#/(StartsTZ|EndsTZ|TimeZone|mutationTz)$#', $path) === 1 && in_array($value, self::UTC_ALIASES, true)) {
				$values[$path] = 'UTC';
			}
		}
		$prefixes = [''];
		foreach (array_keys($values) as $path) {
			if (preg_match('#^(/OccurrenceMutations/[^/]+)/#', $path, $matches) === 1) {
				$prefixes[] = $matches[1];
			}
		}
		foreach (array_unique($prefixes) as $prefix) {
			$duration = $values[$prefix . '/Duration'] ?? null;
			if ($duration === null) {
				continue;
			}
			unset($values[$prefix . '/Duration']);
			if (isset($values[$prefix . '/EndsOn']) || !isset($values[$prefix . '/StartsOn'])) {
				continue;
			}
			$starts = new \DateTimeImmutable($values[$prefix . '/StartsOn']);
			if (isset($values[$prefix . '/StartsTZ'])) {
				$starts = $starts->setTimezone(new \DateTimeZone($values[$prefix . '/StartsTZ']));
				$values[$prefix . '/EndsTZ'] ??= $values[$prefix . '/StartsTZ'];
			}
			$interval = new \DateInterval(ltrim($duration, '-'));
			if (str_starts_with($duration, '-')) {
				$ends = $starts->sub($interval);
			} else {
				$ends = $starts->add($interval);
			}
			$values[$prefix . '/EndsOn'] = $ends->format(DATE_ATOM);
		}
		return $values;
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
