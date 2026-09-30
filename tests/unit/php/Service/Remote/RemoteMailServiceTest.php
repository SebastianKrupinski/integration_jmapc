<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use OCA\JMAPC\Service\Remote\RemoteMailService;
use OCA\JMAPC\Store\Remote\Filters\MailCollectionFilter;
use OCA\JMAPC\Store\Remote\Sort\MailCollectionSort;
use PHPUnit\Framework\TestCase;

class RemoteMailServiceTest extends TestCase {

	private RemoteMailService $mailService;

	public function setUp(): void {
		parent::setUp();
        
        $this->mailService = new RemoteMailService();
	}

	public function testCollectionListFilter(): void {
		// instantiate the filter
		$filter = $this->mailService->collectionListFilter();
		$this->assertInstanceOf(MailCollectionFilter::class, $filter);

		// retrieve attributes
		$attributes = $filter->attributes();
		$this->assertIsArray($attributes);
		$this->assertArrayHasKey('in', $attributes);
		$this->assertArrayHasKey('name', $attributes);
		$this->assertArrayHasKey('role', $attributes);
		$this->assertArrayHasKey('hasRoles', $attributes);
		$this->assertArrayHasKey('subscribed', $attributes);
	}

	public function testCollectionListSort(): void {
		// instantiate the sort
		$sort = $this->mailService->collectionListSort();
		$this->assertInstanceOf(MailCollectionSort::class, $sort);
		
        // retrieve attributes
		$attributes = $sort->attributes();
		$this->assertIsArray($attributes);
		$this->assertArrayHasKey('name', $attributes);
		$this->assertArrayHasKey('order', $attributes);
	}
}
