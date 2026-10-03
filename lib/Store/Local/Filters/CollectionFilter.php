<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Local\Filters;

use OCA\JMAPC\Store\Common\Filters\FilterBase;

class CollectionFilter extends FilterBase {

	protected array $attributes = [
		'id' => true,
		'uid' => true,
		'sid' => true,
		'type' => true,
		'ccid' => true,
		'uuid' => true,
		'label' => true,
		'color' => true,
		'visible' => true,
	];

}
