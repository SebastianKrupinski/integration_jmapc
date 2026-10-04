<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Fixtures;

use PHPUnit\Framework\Assert;

/**
 * Test cases stored as files in a tests/php/resources directory
 *
 * Objects are compared as flattened "path => value" maps. Differences that are
 * known converter issues are listed per fixture and comparison in known-issues.json,
 * a listed issue that no longer occurs fails the comparison so the list stays current.
 */
abstract class ObjectFixtures {

	/** directory holding the fixture files and known-issues.json */
	abstract protected static function directory(): string;

	/** file extension of the fixture files */
	abstract protected static function extension(): string;

	/**
	 * properties assigned by the system or the server, left out at any depth
	 *
	 * @return list<string>
	 */
	abstract protected static function ignored(): array;

	/**
	 * entry properties that only order entries within their collection
	 *
	 * @return list<string>
	 */
	protected static function ignoredEntry(): array {
		return ['Index'];
	}

	/**
	 * Adjusts the flattened values, e.g. to fill in defaults
	 *
	 * @param array<string, scalar> $values
	 * @return array<string, scalar>
	 */
	protected static function normalize(array $values): array {
		return $values;
	}

	protected static function exportDate(string $path, \DateTimeInterface $value): string {
		return $value->format(DATE_ATOM);
	}

	/**
	 * @return array<string, array{string}> fixture name => [fixture name]
	 */
	public static function provider(): array {
		$fixtures = [];
		foreach (glob(static::directory() . '/*.' . static::extension()) as $file) {
			$name = basename($file, '.' . static::extension());
			$fixtures[$name] = [$name];
		}
		return $fixtures;
	}

	/**
	 * @return string contents of the fixture file
	 */
	public static function source(string $name): string {
		return file_get_contents(static::directory() . '/' . $name . '.' . static::extension());
	}

	/**
	 * Runs $operation and collects the warnings and notices raised by the app code instead
	 * of failing on them, errors raised elsewhere go to the previous error handler
	 *
	 * Deprecations are left to PHPUnit, forwarding them from here would make PHPUnit
	 * attribute third party deprecations to the test code
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
	 * Runs $operation like capture() and also collects the exception it throws,
	 * so a conversion that fails can be listed as a known issue under /@error
	 *
	 * @param list<string> $warnings
	 * @return mixed the result of $operation, null when it threw
	 */
	public static function attempt(callable $operation, array &$warnings, ?\Throwable &$error): mixed {
		$error = null;
		try {
			return static::capture($operation, $warnings);
		} catch (\Throwable $e) {
			$error = $e;
			return null;
		}
	}

	/**
	 * Flattens an object into "path => value", leaving out empty values and
	 * properties that are not object data
	 *
	 * @return array<string, scalar>
	 */
	public static function flatten(object $object): array {
		return static::normalize(static::export($object, ''));
	}

	/**
	 * @return array<string, string> path => "expected => actual"
	 */
	public static function differences(object $expected, object $actual): array {
		$expected = static::flatten($expected);
		$actual = static::flatten($actual);
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
		$issues = json_decode(file_get_contents(static::directory() . '/known-issues.json'), true, 512, JSON_THROW_ON_ERROR);
		return $issues[$name][$comparison] ?? [];
	}

	/**
	 * Asserts that $actual holds the same data as $expected apart from the known issues
	 *
	 * @param string $comparison known issue group, e.g. vcard, jmap or server
	 * @param list<string> $warnings warnings raised during the conversions, reported under /@warnings
	 * @param \Throwable|null $error exception that ended the conversion, reported under /@error instead of the differences
	 */
	public static function assertEquivalent(string $name, string $comparison, object $expected, ?object $actual, array $warnings = [], ?\Throwable $error = null): void {
		if ($error !== null || $actual === null) {
			$differences = ['/@error' => $error !== null ? get_class($error) . ': ' . $error->getMessage() : 'no object returned'];
		} else {
			$differences = static::differences($expected, $actual);
		}
		if ($warnings !== []) {
			$differences['/@warnings'] = implode('; ', array_unique($warnings));
		}

		$fixed = [];
		foreach (static::knownIssues($name, $comparison) as $prefix => $reason) {
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
			return [$path => static::exportDate($path, $value)];
		}
		if ($value instanceof \DateTimeZone) {
			return [$path => $value->getName()];
		}
		if ($value instanceof \DateInterval) {
			return [$path => ($value->invert ? '-' : '') . $value->format('P%yY%mM%dDT%hH%iM%sS')];
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

		$isCollection = $value instanceof \Traversable || is_array($value);
		if ($value instanceof \Traversable) {
			$entries = static::keyEntries(iterator_to_array($value));
		} elseif (is_array($value)) {
			$entries = static::keyEntries($value);
		} else {
			$entries = get_object_vars($value);
		}
		$values = [];
		foreach ($entries as $key => $entry) {
			if (!$isCollection) {
				if (in_array($key, static::ignored(), true)) {
					continue;
				}
				if ($path !== '' && in_array($key, static::ignoredEntry(), true)) {
					continue;
				}
				if ($key === 'Priority' && $entry === 0) {
					continue;
				}
			}
			$values += static::export($entry, $path . '/' . $key);
		}
		return $values;
	}

	/**
	 * Keys collection entries by their Id, the way sync identifies them, or by
	 * position when they have none since numbered keys are assigned by each store
	 */
	private static function keyEntries(array $entries): array {
		if ($entries === []) {
			return $entries;
		}
		$keyed = [];
		foreach ($entries as $entry) {
			if (!is_object($entry) || !isset($entry->Id) || $entry->Id === '') {
				$keyed = null;
				break;
			}
			$keyed[(string)$entry->Id] = $entry;
		}
		if ($keyed !== null) {
			return $keyed;
		}
		foreach (array_keys($entries) as $key) {
			if (!is_int($key)) {
				return $entries;
			}
		}
		return array_values($entries);
	}
}
