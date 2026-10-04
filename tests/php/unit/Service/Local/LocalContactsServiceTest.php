<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Local;

use OCA\JMAPC\Objects\Contact\ContactAnniversaryTypes;
use OCA\JMAPC\Objects\Contact\ContactObject;
use OCA\JMAPC\Objects\Contact\ContactOrganizationObject;
use OCA\JMAPC\Objects\Contact\ContactTagCollection;
use OCA\JMAPC\Service\Local\LocalContactsService;
use OCA\JMAPC\Tests\Unit\TestCase;
use Sabre\VObject\Reader;

class LocalContactsServiceTest extends TestCase {

	private LocalContactsService $contactsService;

	public function setUp(): void {
		parent::setUp();
		$this->contactsService = new LocalContactsService();
	}

	public function testToContactObjectPlacesKeepDates(): void {
		$contact = $this->contactsService->toContactObject(Reader::read(
			"BEGIN:VCARD\r\nVERSION:4.0\r\nUID:places\r\nFN:Test\r\n"
			. "BDAY:19850315\r\nBIRTHPLACE:Minneapolis\r\nDEATHDATE:20200101\r\nDEATHPLACE:Boston\r\nEND:VCARD\r\n"
		));

		$birth = $contact->Anniversaries[ContactAnniversaryTypes::Birth->value];
		$this->assertSame(ContactAnniversaryTypes::Birth, $birth->Type);
		$this->assertSame('1985-03-15', $birth->When->format('Y-m-d'));
		$this->assertSame('Minneapolis', $birth->Location);
		$death = $contact->Anniversaries[ContactAnniversaryTypes::Death->value];
		$this->assertSame(ContactAnniversaryTypes::Death, $death->Type);
		$this->assertSame('2020-01-01', $death->When->format('Y-m-d'));
		$this->assertSame('Boston', $death->Location);
	}

	public function testPlacesWithoutDates(): void {
		$contact = $this->contactsService->toContactObject(Reader::read(
			"BEGIN:VCARD\r\nVERSION:4.0\r\nUID:places\r\nFN:Test\r\n"
			. "BIRTHPLACE:Minneapolis\r\nDEATHPLACE:Boston\r\nEND:VCARD\r\n"
		));

		$birth = $contact->Anniversaries[ContactAnniversaryTypes::Birth->value];
		$this->assertSame(ContactAnniversaryTypes::Birth, $birth->Type);
		$this->assertNull($birth->When);
		$this->assertSame('Minneapolis', $birth->Location);
		$this->assertSame(ContactAnniversaryTypes::Death, $contact->Anniversaries[ContactAnniversaryTypes::Death->value]->Type);

		$vcard = $this->contactsService->fromContactObject($contact);
		$this->assertFalse(isset($vcard->BDAY));
		$this->assertFalse(isset($vcard->DEATHDATE));
		$this->assertSame('Minneapolis', (string)$vcard->BIRTHPLACE);
		$this->assertSame('Boston', (string)$vcard->DEATHPLACE);
	}

	public function testToContactObjectOrganizationUnits(): void {
		$contact = $this->contactsService->toContactObject(Reader::read(
			"BEGIN:VCARD\r\nVERSION:4.0\r\nUID:org\r\nFN:Test\r\n"
			. "ORG;X-ID=org-1:ACME Corporation;Engineering;;Research\r\nEND:VCARD\r\n"
		));

		$organization = $contact->Organizations['org-1'];
		$this->assertSame('ACME Corporation', $organization->Label);
		$this->assertSame(['Engineering', 'Research'], iterator_to_array($organization->Units));
	}

	public function testFromContactObjectOrganizationUnits(): void {
		$contact = new ContactObject();
		$organization = new ContactOrganizationObject();
		$organization->Id = 'org-1';
		$organization->Label = 'ACME Corporation';
		$organization->Units[] = 'Engineering';
		$organization->Units[] = 'Research';
		$contact->Organizations['org-1'] = $organization;

		$vcard = $this->contactsService->fromContactObject($contact);

		$this->assertSame(['ACME Corporation', 'Engineering', 'Research'], $vcard->ORG->getParts());
		$this->assertSame('org-1', (string)$vcard->ORG['X-ID']);
		$this->assertCount(1, $vcard->ORG->parameters());
	}

	public function testFromContactObjectTags(): void {
		$contact = new ContactObject();
		$contact->Tags = new ContactTagCollection(['Work', '', 'VIP, Gold']);

		$vcard = $this->contactsService->fromContactObject($contact);

		$this->assertSame(['Work', 'VIP, Gold'], $vcard->CATEGORIES->getParts());
		$this->assertCount(1, $vcard->select('CATEGORIES'));
	}

	public function testFromContactObjectNoTags(): void {
		$vcard = $this->contactsService->fromContactObject(new ContactObject());

		$this->assertFalse(isset($vcard->CATEGORIES));
	}

	public function testToContactObjectTypesLowerCase(): void {
		$contact = $this->contactsService->toContactObject(Reader::read(
			"BEGIN:VCARD\r\nVERSION:3.0\r\nUID:types\r\nFN:Test\r\n"
			. "EMAIL;X-ID=email-1;TYPE=HOME:a@example.com\r\nTEL;X-ID=tel-1;TYPE=WORK,Voice:+1-555-0100\r\nEND:VCARD\r\n"
		));

		$this->assertSame('home', $contact->Email['email-1']->Context);
		$this->assertSame('work,voice', $contact->Phone['tel-1']->Context);
	}
}
