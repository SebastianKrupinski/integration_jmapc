<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

class ContactAliasObject {

	public ?string $Label = null;

	public ?string $Id = null;
	public ?int $Index = null;
	public ?int $Priority = null; // PREF
	public ?string $Context = null; // TYPE
	public ?string $Language = null; // LANGUAGE
	public ?string $URI = null; // VALUE

}
