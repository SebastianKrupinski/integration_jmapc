<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

use DateTimeInterface;
use DateTimeZone;
use OCA\JMAPC\Objects\OriginTypes;

class ContactObject {

	public ?OriginTypes $Origin = null;             // System
	public ?string $ID = null;                      // System Entity Id
	public ?string $CID = null;                     // System Collection Id
	public ?string $Signature = null;               // System Entity Signature
	public ?string $CCID = null;                    // Correlation Collection Id
	public ?string $CEID = null;                    // Correlation Entity Id
	public ?string $CESN = null;                    // Correlation Signature
	public ?string $UUID = null;
	public ?DateTimeInterface $CreatedOn = null;
	public ?DateTimeInterface $ModifiedOn = null;
	public ?string $Kind = null;
	public ?string $Label = null;
	public ContactNameObject $Name;
	public ContactAnniversaryCollection $Anniversaries;
	public ContactPronounCollection $Pronouns;
	public ContactPhoneCollection $Phone;
	public ContactEmailCollection $Email;
	public ContactPhysicalLocationCollection $PhysicalLocations;
	public ContactOrganizationCollection $Organizations;
	public ContactTitleCollection $Titles;
	public ContactTagCollection $Tags;
	public ContactNoteCollection $Notes;
	public ?string $Partner = null;
	public ?string $Language = null;
	public ContactLanguageCollection $Languages;
	public ?DateTimeZone $TimeZone = null;
	public ContactCryptoCollection $Crypto;
	public ContactVirtualLocationCollection $VirtualLocations;
	public ?array $Other = [];

	public function __construct() {
		$this->Name = new ContactNameObject();
		$this->Anniversaries = new ContactAnniversaryCollection();
		$this->Pronouns = new ContactPronounCollection();
		$this->Phone = new ContactPhoneCollection();
		$this->Email = new ContactEmailCollection();
		$this->PhysicalLocations = new ContactPhysicalLocationCollection();
		$this->Organizations = new ContactOrganizationCollection();
		$this->Titles = new ContactTitleCollection();
		$this->Tags = new ContactTagCollection();
		$this->Notes = new ContactNoteCollection();
		$this->Crypto = new ContactCryptoCollection();
		$this->VirtualLocations = new ContactVirtualLocationCollection();
	}

}
