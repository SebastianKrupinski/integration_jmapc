<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Remote\Sort;

use OCA\JMAPC\Store\Common\Sort\SortBase;

class MailCollectionSort extends SortBase {

	protected array $attributes = [
		'name' => true,
		'order' => true,
	];

}
