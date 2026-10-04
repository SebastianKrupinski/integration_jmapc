<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service;

use JmapClient\Responses\Calendar\EventParameters as EventParametersResponse;
use OCA\JMAPC\Service\Local\LocalEventsService;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use OCA\JMAPC\Tests\Unit\Fixtures\EventFixtures;
use OCA\JMAPC\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\VObject\Reader;

class EventConversionTest extends TestCase {

	public static function fixtures(): array {
		return EventFixtures::provider();
	}

	/**
	 * iCalendar -> event -> iCalendar text -> event
	 */
	#[DataProvider('fixtures')]
	public function testICalendarRoundTrip(string $name): void {
		$warnings = [];
		$expected = EventFixtures::event($name, $warnings);

		$service = new LocalEventsService();
		$actual = EventFixtures::attempt(
			static fn () => $service->toEventObject(Reader::read($service->fromEventObject($expected)->serialize())),
			$warnings,
			$error,
		);

		EventFixtures::assertEquivalent($name, 'icalendar', $expected, $actual, $warnings, $error);
	}

	/**
	 * event -> JSCalendar request -> JSON -> JSCalendar response -> event
	 */
	#[DataProvider('fixtures')]
	public function testJmapRoundTrip(string $name): void {
		$warnings = [];
		$expected = EventFixtures::event($name, $warnings);

		$service = new RemoteEventsService();
		$actual = EventFixtures::attempt(static function () use ($service, $expected) {
			$event = null;
			$service->fromEventObject($expected)->bind($event);
			$wire = json_decode(json_encode($event, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
			return $service->toEventObject(new EventParametersResponse($wire));
		}, $warnings, $error);

		EventFixtures::assertEquivalent($name, 'jmap', $expected, $actual, $warnings, $error);
	}
}
