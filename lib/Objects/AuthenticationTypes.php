<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects;

enum AuthenticationTypes: string {
	case Basic = 'BA';
	case Bearer = 'OA';
	case Cookie = 'CA';
	case JsonBasic = 'JB';
	case JsonBasicCookie = 'JBC';
}
