<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Fixtures;

use OCA\JMAPC\Objects\Contact\ContactObject;
use OCA\JMAPC\Service\Local\LocalContactsService;
use PHPUnit\Framework\Assert;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

/**
 * Contact test cases stored as vCards in tests/php/resources/contacts
 *
 * Contacts are compared as flattened "path => value" maps. Differences that are
 * known converter issues are listed per fixture and comparison in known-issues.json,
 * a listed issue that no longer occurs fails the comparison so the list stays current.
 */
final class ContactFixtures {
	private const DIRECTORY = __DIR__ . '/../../resources/contacts';
	/** properties assigned by the system or the server */
	private const IGNORED = ['Origin', 'ID', 'CID', 'Signature', 'CCID', 'CEID', 'CESN', 'CreatedOn', 'ModifiedOn'];
	/** entry properties that only order entries within their collection */
	private const IGNORED_ENTRY = ['Index'];

	/**
	 * @return array<string, array{string}> fixture name => [fixture name]
	 */
	public static function provider(): array {
		$fixtures = [];
		foreach (glob(self::DIRECTORY . '/*.vcf') as $file) {
			$name = basename($file, '.vcf');
			$fixtures[$name] = [$name];
		}
		return $fixtures;
	}

	public static function vcard(string $name): VCard {
		return Reader::read(file_get_contents(self::DIRECTORY . '/' . $name . '.vcf'));
	}

	/**
	 * @param list<string> $warnings receives the warnings raised during conversion
	 */
	public static function contact(string $name, array &$warnings): ContactObject {
		return self::capture(static fn () => (new LocalContactsService())->toContactObject(self::vcard($name)), $warnings);
	}

	/**
	 * Runs $operation and collects the warnings and notices raised by the app code instead
	 * of failing on them, errors raised elsewhere go to the previous error handler
	 *
	 * @param list<string> $warnings
	 */
	public static function capture(callable $operation, array &$warnings): mixed {
		$source = dirname(__DIR__, 4) . '/lib/';
		$previous = null;
		$previous = set_error_handler(static function (int $level, string $message, string $file = '', int $line = 0) use (&$warnings, &$previous, $source): bool {
			if (str_starts_with($file, $source)) {
				$warnings[] = $message;
				return true;
			}
			return $previous !== null && $previous($level, $message, $file, $line) !== false;
		}, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);
		try {
			return $operation();
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Flattens a contact into "path => value", leaving out empty values and
	 * properties that are not contact data
	 *
	 * @return array<string, scalar>
	 */
	public static function flatten(ContactObject $contact): array {
		$values = self::export($contact, '');
		foreach (self::IGNORED as $property) {
			unset($values['/' . $property]);
		}
		$values['/Kind'] ??= 'individual';
		return $values;
	}

	/**
	 * @return array<string, string> path => "expected => actual"
	 */
	public static function differences(ContactObject $expected, ContactObject $actual): array {
		$expected = self::flatten($expected);
		$actual = self::flatten($actual);
		$differences = [];
		foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $path) {
			$left = $expected[$path] ?? null;
			$right = $actual[$path] ?? null;
			if ($left !== $right) {
				$differences[$path] = json_encode($left, JSON_UNESCAPED_UNICODE) . ' => ' . json_encode($right, JSON_UNESCAPED_UNICODE);
			}
		}
		ksort($differences);
		return $differences;
	}

	/**
	 * @return array<string, string> path prefix => reason
	 */
	public static function knownIssues(string $name, string $comparison): array {
		$issues = json_decode(file_get_contents(self::DIRECTORY . '/known-issues.json'), true, 512, JSON_THROW_ON_ERROR);
		return $issues[$name][$comparison] ?? [];
	}

	/**
	 * Asserts that $actual holds the same contact data as $expected apart from the known issues
	 *
	 * @param string $comparison known issue group, e.g. vcard, jmap or server
	 * @param list<string> $warnings warnings raised during the conversions, reported under /@warnings
	 */
	public static function assertEquivalent(string $name, string $comparison, ContactObject $expected, ContactObject $actual, array $warnings = []): void {
		$differences = self::differences($expected, $actual);
		if ($warnings !== []) {
			$differences['/@warnings'] = implode('; ', array_unique($warnings));
		}

		$fixed = [];
		foreach (self::knownIssues($name, $comparison) as $prefix => $reason) {
			$matched = false;
			foreach (array_keys($differences) as $path) {
				if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
					unset($differences[$path]);
					$matched = true;
				}
			}
			if (!$matched) {
				$fixed[] = $prefix . ' (' . $reason . ')';
			}
		}

		Assert::assertSame([], $fixed, "Known $comparison issues of $name no longer occur, remove them from known-issues.json");
		Assert::assertSame([], $differences, "Unexpected $comparison differences for $name");
	}

	/**
	 * @return array<string, scalar>
	 */
	private static function export(mixed $value, string $path): array {
		if ($value === null || $value === '' || $value === []) {
			return [];
		}
		if ($value instanceof \DateTimeInterface) {
			// anniversaries are dates, their time of day is not contact data
			return [$path => $value->format(str_starts_with($path, '/Anniversaries/') ? 'Y-m-d' : DATE_ATOM)];
		}
		if ($value instanceof \DateTimeZone) {
			return [$path => $value->getName()];
		}
		if ($value instanceof \BackedEnum) {
			return [$path => $value->value];
		}
		if ($value instanceof \UnitEnum) {
			return [$path => $value->name];
		}
		if (is_scalar($value)) {
			return [$path => $value];
		}

		$entries = $value instanceof \Traversable ? iterator_to_array($value) : (is_object($value) ? get_object_vars($value) : $value);
		if ($value instanceof \Traversable || is_array($value)) {
			$entries = self::keyEntries($entries);
		}
		$values = [];
		foreach ($entries as $key => $entry) {
			if (is_object($value) && !$value instanceof \Traversable && $path !== '' && in_array($key, self::IGNORED_ENTRY, true)) {
				continue;
			}
			if ($key === 'Priority' && $entry === 0) {
				continue;
			}
			$values += self::export($entry, $path . '/' . $key);
		}
		return $values;
	}

	/**
	 * Keys collection entries by their Id, the way sync identifies them, or by
	 * position when they have none since numbered keys are assigned by each store
	 */
	private static function keyEntries(array $entries): array {
		$ids = array_map(static fn ($entry) => is_object($entry) && isset($entry->Id) && $entry->Id !== '' ? (string)$entry->Id : null, $entries);
		if ($entries !== [] && !in_array(null, $ids, true)) {
			return array_combine($ids, $entries);
		}
		if (array_filter(array_keys($entries), 'is_int') === array_keys($entries)) {
			return array_values($entries);
		}
		return $entries;
	}
}
