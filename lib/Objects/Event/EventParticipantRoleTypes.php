<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

enum EventParticipantRoleTypes: string {
	case Owner = 'owner';
	case Chair = 'chair';
	case Attendee = 'attendee';
	case Optional = 'optional';
	case Informational = 'informational';
	case Contact = 'contact';
}
