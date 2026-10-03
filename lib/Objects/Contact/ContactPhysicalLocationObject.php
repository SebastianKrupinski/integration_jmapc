<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

class ContactPhysicalLocationObject {

	public ?string $Box = null;
	public ?string $Unit = null;
	public ?string $Street = null;
	public ?string $Locality = null;
	public ?string $Region = null;
	public ?string $Code = null;
	public ?string $Country = null;

	public ?string $Label = null;
	public ?string $Coordinates = null;
	public ?string $TimeZone = null;

	public ?string $Id = null;
	public ?int $Index = null;
	public ?int $Priority = null;
	public ?string $Context = null;
	public ?string $Language = null;
	public ?string $URI = null;

}
