<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers;

/**
 * This is the interface that is implemented by apps that
 * implement a mail provider
 *
 * @since 30.0.0
 */
interface IServiceLocation {
	/**
	 *
	 * @since 30.0.0
	 */
	public function type(): string;

}
