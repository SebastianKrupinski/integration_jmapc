<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

class EventParticipantObject {

	public ?string $Id = null;
	public ?string $Name = null;
	public ?string $Description = null;
	public ?string $Language = null;
	public ?string $Address = null;
	public ?EventParticipantTypes $Type = null;
	public ?EventParticipantStatusTypes $Status = null;
	public ?string $Comment = null;
	public EventParticipantRoleCollection $Roles;

	public function __construct() {
		$this->Roles = new EventParticipantRoleCollection();
	}

}
