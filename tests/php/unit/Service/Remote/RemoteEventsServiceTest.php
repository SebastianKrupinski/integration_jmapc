<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use JmapClient\Responses\Calendar\EventParameters as EventParametersResponse;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use OCA\JMAPC\Store\Remote\Filters\EventFilter;
use OCA\JMAPC\Store\Remote\Sort\EventSort;
use PHPUnit\Framework\TestCase;

class RemoteEventsServiceTest extends TestCase {

	private RemoteEventsService $eventsService;

	public function setUp(): void {
		parent::setUp();

		// Instantiate the RemoteEventsService
		$this->eventsService = new RemoteEventsService();
	}

	public function testEntityListFilter(): void {
		// instantiate the filter
		$filter = $this->eventsService->entityListFilter();
		$this->assertInstanceOf(EventFilter::class, $filter);

		// retrieve attributes
		$attributes = $filter->attributes();
		$this->assertIsArray($attributes);

		// Assert that the expected attributes are present
		$this->assertArrayHasKey('before', $attributes);
		$this->assertArrayHasKey('after', $attributes);
		$this->assertArrayHasKey('uid', $attributes);
		$this->assertArrayHasKey('text', $attributes);
		$this->assertArrayHasKey('title', $attributes);
		$this->assertArrayHasKey('description', $attributes);
		$this->assertArrayHasKey('location', $attributes);
		$this->assertArrayHasKey('owner', $attributes);
		$this->assertArrayHasKey('attendee', $attributes);
	}

	public function testEntityListSort(): void {
		// instantiate the sort
		$sort = $this->eventsService->entityListSort();
		$this->assertInstanceOf(EventSort::class, $sort);

		// retrieve attributes
		$attributes = $sort->attributes();
		$this->assertIsArray($attributes);

		// Assert that the expected attributes are present
		$this->assertArrayHasKey('created', $attributes);
		$this->assertArrayHasKey('modified', $attributes);
		$this->assertArrayHasKey('start', $attributes);
		$this->assertArrayHasKey('uid', $attributes);
		$this->assertArrayHasKey('recurrence', $attributes);
	}

	public function testFromEventObjectStartTimeZone(): void {
		$event = new EventObject();
		$event->StartsOn = new \DateTimeImmutable('2026-11-12T14:00:00', new \DateTimeZone('Europe/Berlin'));
		$event->StartsTZ = new \DateTimeZone('Europe/Berlin');
		$event->EndsOn = new \DateTimeImmutable('2026-11-12T15:30:00', new \DateTimeZone('Europe/Berlin'));

		$card = null;
		$this->eventsService->fromEventObject($event)->bind($card);

		$this->assertSame('Europe/Berlin', $card->timeZone);
		$this->assertSame('2026-11-12T14:00:00', $card->start);
		$this->assertSame('PT1H30M', $card->duration);
	}

	public function testFromEventObjectStartConvertedToEventTimeZone(): void {
		$event = new EventObject();
		$event->StartsOn = new \DateTimeImmutable('2026-11-12T13:00:00', new \DateTimeZone('UTC'));
		$event->TimeZone = new \DateTimeZone('Europe/Berlin');

		$card = null;
		$this->eventsService->fromEventObject($event)->bind($card);

		$this->assertSame('Europe/Berlin', $card->timeZone);
		$this->assertSame('2026-11-12T14:00:00', $card->start);
	}

	public function testToEventObjectStartTimeZone(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'timeZone' => 'Europe/Berlin',
			'start' => '2026-11-12T14:00:00',
			'duration' => 'PT1H30M',
		]));

		$this->assertSame('2026-11-12T14:00:00+01:00', $event->StartsOn->format(DATE_ATOM));
		$this->assertSame('Europe/Berlin', $event->StartsTZ->getName());
		$this->assertSame('2026-11-12T15:30:00+01:00', $event->EndsOn->format(DATE_ATOM));
		$this->assertSame('Europe/Berlin', $event->EndsTZ->getName());
		$this->assertNull($event->TimeZone);
	}

	public function testToEventObjectMutationWithoutTimeZone(): void {
		$event = $this->eventsService->toEventObject(new EventParametersResponse([
			'calendarIds' => ['calendar-1' => true],
			'start' => '2026-11-04T09:00:00',
			'duration' => 'PT30M',
			'recurrenceOverrides' => [
				'2026-11-11T09:00:00' => ['start' => '2026-11-11T13:00:00'],
			],
		]));

		$this->assertCount(1, $event->OccurrenceMutations);
		$this->assertNull($event->OccurrenceMutations['2026-11-11T09:00:00']->mutationTz);
	}
}
