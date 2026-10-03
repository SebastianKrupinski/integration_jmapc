<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Common\Range;

interface IRangeTally extends IRange {

	/**
	 *
	 * @since 1.0.0
	 */
	public function anchor(): RangeAnchorType;

	/**
	 *
	 * @since 1.0.0
	 */
	public function getPosition(): string|int;

	/**
	 *
	 * @since 1.0.0
	 */
	public function setPosition(string|int $value): void;

	/**
	 *
	 * @since 1.0.0
	 */
	public function getCount(): int;

	/**
	 *
	 * @since 1.0.0
	 */
	public function setCount(int $value): void;

}
