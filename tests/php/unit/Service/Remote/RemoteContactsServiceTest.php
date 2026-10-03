<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use OCA\JMAPC\Service\Remote\RemoteContactsService;
use OCA\JMAPC\Store\Remote\Filters\ContactFilter;
use OCA\JMAPC\Store\Remote\Sort\ContactSort;
use PHPUnit\Framework\TestCase;

class RemoteContactsServiceTest extends TestCase {

	private RemoteContactsService $contactsService;

	public function setUp(): void {
		parent::setUp();

		// Instantiate the RemoteContactsService
		$this->contactsService = new RemoteContactsService();
	}

	public function testEntityListFilter(): void {
		// instantiate the filter
		$filter = $this->contactsService->entityListFilter();
		$this->assertInstanceOf(ContactFilter::class, $filter);

		// retrieve attributes
		$attributes = $filter->attributes();
		$this->assertIsArray($attributes);

		// Assert that the expected attributes are present
		$this->assertArrayHasKey('createBefore', $attributes);
		$this->assertArrayHasKey('createAfter', $attributes);
		$this->assertArrayHasKey('modifiedBefore', $attributes);
		$this->assertArrayHasKey('modifiedAfter', $attributes);
		$this->assertArrayHasKey('uid', $attributes);
		$this->assertArrayHasKey('kind', $attributes);
		$this->assertArrayHasKey('member', $attributes);
		$this->assertArrayHasKey('text', $attributes);
		$this->assertArrayHasKey('name', $attributes);
		$this->assertArrayHasKey('nameGiven', $attributes);
		$this->assertArrayHasKey('nameSurname', $attributes);
		$this->assertArrayHasKey('nameAlias', $attributes);
		$this->assertArrayHasKey('organization', $attributes);
		$this->assertArrayHasKey('email', $attributes);
		$this->assertArrayHasKey('phone', $attributes);
		$this->assertArrayHasKey('address', $attributes);
		$this->assertArrayHasKey('note', $attributes);
	}

	public function testEntityListSort(): void {
		// instantiate the sort
		$sort = $this->contactsService->entityListSort();
		$this->assertInstanceOf(ContactSort::class, $sort);

		// retrieve attributes
		$attributes = $sort->attributes();
		$this->assertIsArray($attributes);

		// Assert that the expected attributes are present
		$this->assertArrayHasKey('created', $attributes);
		$this->assertArrayHasKey('modified', $attributes);
		$this->assertArrayHasKey('nameGiven', $attributes);
		$this->assertArrayHasKey('nameSurname', $attributes);
	}
}
