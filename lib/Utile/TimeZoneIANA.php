<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Utile;

class TimeZoneIANA {

	private $zones = [
	];

	public static function findByName(?string $name) : ?object {
		$r = array_search($name, array_column($zones, 'id'));
		if (isset($r)) {
			return (object)$zone[$r];
		} else {
			return null;
		}
	}

}
