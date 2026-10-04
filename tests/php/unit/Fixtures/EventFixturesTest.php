<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Fixtures;

use OCA\JMAPC\Objects\Event\EventMutationObject;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Tests\Unit\TestCase;

class EventFixturesTest extends TestCase {

	private static function event(string $starts, ?string $ends = null, ?string $duration = null): EventObject {
		$event = new EventObject();
		$event->StartsOn = new \DateTimeImmutable($starts, new \DateTimeZone('Europe/Berlin'));
		$event->StartsTZ = new \DateTimeZone('Europe/Berlin');
		if ($ends !== null) {
			$event->EndsOn = new \DateTimeImmutable($ends, new \DateTimeZone('Europe/Berlin'));
			$event->EndsTZ = new \DateTimeZone('Europe/Berlin');
		}
		if ($duration !== null) {
			$event->Duration = new \DateInterval($duration);
		}
		return $event;
	}

	public function testEndAndDurationAreEquivalent(): void {
		$withEnd = self::event('2026-11-12T14:00:00', ends: '2026-11-12T15:30:00');
		$withDuration = self::event('2026-11-12T14:00:00', duration: 'PT1H30M');
		$withBoth = self::event('2026-11-12T14:00:00', ends: '2026-11-12T15:30:00', duration: 'PT1H30M');

		$this->assertSame([], EventFixtures::differences($withEnd, $withDuration));
		$this->assertSame([], EventFixtures::differences($withDuration, $withBoth));
	}

	public function testDifferentEndIsReported(): void {
		$withEnd = self::event('2026-11-12T14:00:00', ends: '2026-11-12T15:30:00');
		$withDuration = self::event('2026-11-12T14:00:00', duration: 'PT2H');

		$this->assertSame(
			['/EndsOn' => '"2026-11-12T15:30:00+01:00" => "2026-11-12T16:00:00+01:00"'],
			EventFixtures::differences($withEnd, $withDuration),
		);
	}

	public function testMutationEndAndDurationAreEquivalent(): void {
		$withEnd = self::event('2026-11-04T09:00:00', ends: '2026-11-04T09:30:00');
		$mutation = new EventMutationObject();
		$mutation->StartsOn = new \DateTimeImmutable('2026-11-11T13:00:00', new \DateTimeZone('Europe/Berlin'));
		$mutation->EndsOn = new \DateTimeImmutable('2026-11-11T13:30:00', new \DateTimeZone('Europe/Berlin'));
		$withEnd->OccurrenceMutations['2026-11-11T09:00:00'] = $mutation;

		$withDuration = self::event('2026-11-04T09:00:00', duration: 'PT30M');
		$mutation = new EventMutationObject();
		$mutation->StartsOn = new \DateTimeImmutable('2026-11-11T13:00:00', new \DateTimeZone('Europe/Berlin'));
		$mutation->Duration = new \DateInterval('PT30M');
		$withDuration->OccurrenceMutations['2026-11-11T09:00:00'] = $mutation;

		$this->assertSame([], EventFixtures::differences($withEnd, $withDuration));
	}

	public function testUtcAliasesAreEquivalent(): void {
		$utc = self::event('2026-11-10T09:00:00');
		$utc->StartsOn = new \DateTimeImmutable('2026-11-10T09:00:00', new \DateTimeZone('UTC'));
		$utc->StartsTZ = new \DateTimeZone('UTC');
		$etc = self::event('2026-11-10T09:00:00');
		$etc->StartsOn = new \DateTimeImmutable('2026-11-10T09:00:00', new \DateTimeZone('Etc/UTC'));
		$etc->StartsTZ = new \DateTimeZone('Etc/UTC');

		$this->assertSame([], EventFixtures::differences($utc, $etc));
	}

	public function testOtherTimeZonesAreReported(): void {
		$berlin = self::event('2026-11-10T09:00:00');
		$paris = self::event('2026-11-10T09:00:00');
		$paris->StartsTZ = new \DateTimeZone('Europe/Paris');

		$this->assertSame(['/StartsTZ' => '"Europe\/Berlin" => "Europe\/Paris"'], EventFixtures::differences($berlin, $paris));
	}
}
