<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

use OCA\JMAPC\Objects\BaseStringCollection;

class ContactOrganizationObject {

	public ?string $Label;
	public BaseStringCollection $Units;

	public ?string $SortName = null;

	public ?string $Id = null;
	public ?int $Index = null;
	public ?int $Priority = null;
	public ?string $Context = null;
	public ?string $Language = null;
	public ?string $URI = null;

	public function __construct() {
		$this->Units = new BaseStringCollection();
	}

}
