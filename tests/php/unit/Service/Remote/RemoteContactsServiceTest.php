<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use JmapClient\Responses\Contacts\ContactParameters as ContactParametersResponse;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryObject;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryTypes;
use OCA\JMAPC\Objects\Contact\ContactObject;
use OCA\JMAPC\Objects\Contact\ContactPhysicalLocationObject;
use OCA\JMAPC\Objects\Contact\ContactTagCollection;
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

	public function testFromContactObjectAnniversaries(): void {
		$contact = new ContactObject();
		$birth = new ContactAnniversaryObject();
		$birth->Type = ContactAnniversaryTypes::Birth;
		$birth->Location = 'Minneapolis';
		$contact->Anniversaries['birth'] = $birth;
		$nuptial = new ContactAnniversaryObject();
		$nuptial->Type = ContactAnniversaryTypes::Nuptial;
		$contact->Anniversaries['nuptial'] = $nuptial;

		$card = null;
		$this->contactsService->fromContactObject($contact)->bind($card);

		$this->assertSame('birth', $card->anniversaries->birth->kind);
		$this->assertSame('Minneapolis', $card->anniversaries->birth->place->full);
		$this->assertSame('wedding', $card->anniversaries->nuptial->kind);
		$this->assertObjectNotHasProperty('place', $card->anniversaries->nuptial);
	}

	public function testToContactObjectAnniversaries(): void {
		$contact = $this->contactsService->toContactObject(new ContactParametersResponse([
			'anniversaries' => [
				'birth' => ['kind' => 'birth', 'place' => ['full' => 'Minneapolis']],
				'nuptial' => ['kind' => 'wedding'],
				'other' => ['kind' => 'unknown'],
			],
		]));

		$this->assertSame(ContactAnniversaryTypes::Birth, $contact->Anniversaries['birth']->Type);
		$this->assertSame('Minneapolis', $contact->Anniversaries['birth']->Location);
		$this->assertSame(ContactAnniversaryTypes::Nuptial, $contact->Anniversaries['nuptial']->Type);
		$this->assertNull($contact->Anniversaries['nuptial']->Location);
		$this->assertNull($contact->Anniversaries['other']->Type);
	}

	public function testFromContactObjectAddressContext(): void {
		$contact = new ContactObject();
		foreach (['home' => 'HOME', 'work' => 'work', 'other' => 'postal'] as $id => $context) {
			$address = new ContactPhysicalLocationObject();
			$address->Locality = 'Springfield';
			$address->Context = $context;
			$contact->PhysicalLocations[$id] = $address;
		}

		$card = null;
		$this->contactsService->fromContactObject($contact)->bind($card);

		$this->assertEquals((object)['private' => true], $card->addresses->home->contexts);
		$this->assertEquals((object)['work' => true], $card->addresses->work->contexts);
		$this->assertObjectNotHasProperty('contexts', $card->addresses->other);
	}

	public function testToContactObjectAddressContext(): void {
		$contact = $this->contactsService->toContactObject(new ContactParametersResponse([
			'addresses' => [
				'home' => ['contexts' => ['private' => true]],
				'work' => ['contexts' => ['work' => true]],
				'none' => [],
			],
		]));

		$this->assertSame('home', $contact->PhysicalLocations['home']->Context);
		$this->assertSame('work', $contact->PhysicalLocations['work']->Context);
		$this->assertNull($contact->PhysicalLocations['none']->Context);
	}

	public function testFromContactObjectFullName(): void {
		$contact = new ContactObject();
		$contact->Label = 'Dr. Eva Maria von Berg Jr.';

		$card = null;
		$this->contactsService->fromContactObject($contact)->bind($card);

		$this->assertSame('Dr. Eva Maria von Berg Jr.', $card->name->full);
	}

	public function testToContactObjectFullName(): void {
		$contact = $this->contactsService->toContactObject(new ContactParametersResponse([
			'name' => ['full' => 'Dr. Eva Maria von Berg Jr.'],
		]));

		$this->assertSame('Dr. Eva Maria von Berg Jr.', $contact->Label);
	}

	public function testFromContactObjectTags(): void {
		$contact = new ContactObject();
		$contact->Tags = new ContactTagCollection(['Work', '', 'VIP']);

		$card = null;
		$this->contactsService->fromContactObject($contact)->bind($card);

		$this->assertEquals((object)['Work' => true, 'VIP' => true], $card->keywords);
	}

	public function testFromContactObjectNoTags(): void {
		$card = null;
		$this->contactsService->fromContactObject(new ContactObject())->bind($card);

		$this->assertObjectNotHasProperty('keywords', $card);
	}

	public function testToContactObjectTags(): void {
		$contact = $this->contactsService->toContactObject(new ContactParametersResponse([
			'keywords' => ['Work' => true, 'VIP' => true],
		]));

		$this->assertSame(['Work', 'VIP'], iterator_to_array($contact->Tags));
	}

	public function testFromContactObjectLanguage(): void {
		$contact = new ContactObject();
		$contact->Language = 'de';

		$card = null;
		$this->contactsService->fromContactObject($contact)->bind($card);

		$this->assertSame('de', $card->language);
	}

	public function testToContactObjectLanguage(): void {
		$contact = $this->contactsService->toContactObject(new ContactParametersResponse([
			'language' => 'de',
		]));

		$this->assertSame('de', $contact->Language);
	}

	public function testFromContactObjectAddressLabel(): void {
		$contact = new ContactObject();
		$address = new ContactPhysicalLocationObject();
		$address->Label = "123 Main Street\nSpringfield, IL 62701";
		$contact->PhysicalLocations['adr-1'] = $address;

		$card = null;
		$this->contactsService->fromContactObject($contact)->bind($card);

		$this->assertSame("123 Main Street\nSpringfield, IL 62701", $card->addresses->{'adr-1'}->full);
	}

	public function testToContactObjectAddressLabel(): void {
		$contact = $this->contactsService->toContactObject(new ContactParametersResponse([
			'addresses' => [
				'adr-1' => ['full' => "123 Main Street\nSpringfield, IL 62701"],
			],
		]));

		$this->assertSame("123 Main Street\nSpringfield, IL 62701", $contact->PhysicalLocations['adr-1']->Label);
	}
}
