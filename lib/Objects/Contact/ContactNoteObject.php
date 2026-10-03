<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

use DateTimeInterface;

class ContactNoteObject {

	public ?string $Content = null;
	public ?DateTimeInterface $Date = null;
	public ?string $AuthorUri = null;
	public ?string $AuthorName = null;

	public ?string $Id = null;
	public ?int $Index = null;
	public ?int $Priority = null;
	public ?string $Context = null;
	public ?string $Language = null;
	public ?string $URI = null;

}
