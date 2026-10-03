<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Local\Filters;

use OCA\JMAPC\Store\Common\Filters\FilterBase;

class ContactFilter extends FilterBase {

	protected array $attributes = [
		'uid' => true,
		'sid' => true,
		'cid' => true,
		'uid' => true,
		'uuid' => true,
		'label' => true,
	];

}
