<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Common\Range;

interface IRange {

	/**
	 *
	 * @since 1.0.0
	 */
	public function type(): string;

}
