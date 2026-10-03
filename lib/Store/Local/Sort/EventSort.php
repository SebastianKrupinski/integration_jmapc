<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Local\Sort;

use OCA\JMAPC\Store\Common\Sort\SortBase;

class EventSort extends SortBase {

	protected array $attributes = [
		'uid' => true,
		'sid' => true,
		'cid' => true,
		'uid' => true,
		'uuid' => true,
		'startson' => true,
		'endson' => true,
		'label' => true,
		'description' => true,
	];

}
