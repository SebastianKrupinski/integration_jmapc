<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Unit\Service\Remote;

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
}
