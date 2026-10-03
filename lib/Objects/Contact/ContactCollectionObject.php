<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

class ContactCollectionObject {

	public string $Id;
	public ?string $Label = null;
	public ?string $Description = null;
	public ?int $Priority = null;
	public ?bool $Visibility = null;
	public ?string $Color = null;
	public ?string $Signature = null;

}
