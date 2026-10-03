<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

use DateTimeInterface;

class ContactAnniversaryObject {

	public ?ContactAnniversaryTypes $Type = null;
	public ?DateTimeInterface $When = null;
	public ?string $Location = null;

}
