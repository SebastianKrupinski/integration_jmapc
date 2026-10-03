<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

class EventLocationVirtualObject {

	public ?string $Id = null;
	public ?string $Name = null;
	public ?string $Description = null;
	public ?string $Relation = null;
	public ?string $Location = null;
	public ?string $TimeZone = null;
	public ?string $Coordinates = null;
	public array $Features = [];

}
