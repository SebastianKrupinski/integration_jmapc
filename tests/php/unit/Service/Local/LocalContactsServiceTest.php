<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Local;

use OCA\JMAPC\Objects\Contact\ContactAnniversaryTypes;
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
}
