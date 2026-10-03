<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

class ContactNameObject {

	public ?string $Last = null;
	public ?string $First = null;
	public ?string $Other = null;
	public ?string $Prefix = null;
	public ?string $Suffix = null;
	public ?string $PhoneticLast = null;
	public ?string $PhoneticFirst = null;
	public ?string $PhoneticOther = null;
	public ContactAliasCollection $Aliases;

	public function __construct() {
		$this->Aliases = new ContactAliasCollection();
	}

}
