<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Integration\Mail;

use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use Test\TestCase;

class ContactsCollectionTest extends TestCase {

	private ServiceEntity $service;

	public function setUp(): void {
		parent::setUp();
		// Load the credentials from the JSON file
		$servicesFile = __DIR__ . '/../../resources/services.json';
		$servicesData = file_get_contents($servicesFile);
		$servicesData = json_decode($servicesData, true);
		$service = new ServiceEntity();
		$this->service = $service->fromRow($servicesData[0]);
	}

	public function testCollectionList(): void {

		$remoteClient = RemoteService::freshClient($this->service);
		$contactsService = RemoteService::contactsService($remoteClient);

		$collections = $contactsService->collectionList();

		$this->assertNotEmpty($collections);
	
	}

}
