<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Remote\Filters;

use OCA\JMAPC\Store\Common\Filters\FilterBase;

class MailCollectionFilter extends FilterBase {

	protected array $attributes = [
		'in' => true,
		'name' => true,
		'role' => true,
		'hasRoles' => true,
		'subscribed' => true,
	];

}
