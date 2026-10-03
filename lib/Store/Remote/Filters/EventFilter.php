<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Remote\Filters;

use OCA\JMAPC\Store\Common\Filters\FilterBase;

class EventFilter extends FilterBase {

	protected array $attributes = [
		'before' => true,
		'after' => true,
		'uid' => true,
		'text' => true,
		'title' => true,
		'description' => true,
		'location' => true,
		'owner' => true,
		'attendee' => true,
	];

}
