<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

class ContactCryptoObject {

	public ?string $Data = null;
	public ?string $Type = null;

	public ?string $Id = null;
	public ?int $Index = null;
	public ?int $Priority = null;
	public ?string $Context = null;

}
