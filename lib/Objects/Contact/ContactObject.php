<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\JMAPC\Objects\Contact;

use DateTimeInterface;
use DateTimeZone;
use OCA\JMAPC\Objects\OriginTypes;

class ContactObject {

	public OriginTypes|null $Origin = null;             // System
	public string|null $ID = null;                      // System Entity Id
	public string|null $CID = null;                     // System Collection Id
	public string|null $Signature = null;               // System Entity Signature
	public string|null $CCID = null;                    // Correlation Collection Id
	public string|null $CEID = null;                    // Correlation Entity Id
	public string|null $CESN = null;                    // Correlation Signature
	public string|null $UUID = null;
	public ?DateTimeInterface $CreatedOn = null;
	public ?DateTimeInterface $ModifiedOn = null;
	public string|null $Kind = null;
	public string|null $Label = null;
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
	public string|null $Partner = null;
	public string|null $Language = null;
	public ContactLanguageCollection $Languages;
	public DateTimeZone|null $TimeZone = null;
	public ContactCryptoCollection $Crypto;
	public ContactVirtualLocationCollection $VirtualLocations;
	public array|null $Other = [];
	
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
