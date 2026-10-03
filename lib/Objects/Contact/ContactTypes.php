<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

enum ContactTypes: string {
	case Individual = 'i';
	case Group = 'g';
	case Organization = 'o';
	case Location = 'l';
	case Device = 'd';
	case Application = 'a';
}
