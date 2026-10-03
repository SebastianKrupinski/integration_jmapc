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
interface IServiceLocationUri extends IServiceLocation {
	/**
	 *
	 * @since 30.0.0
	 */
	public function location(): string;

	/**
	 *
	 * @since 30.0.0
	 */
	public function getScheme(): string;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setScheme(string $value);

	/**
	 *
	 * @since 30.0.0
	 */
	public function getHost(): string;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setHost(string $value);

	/**
	 *
	 * @since 30.0.0
	 */
	public function getPort(): int;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setPort(int $value);

	/**
	 *
	 * @since 30.0.0
	 */
	public function getPath(): string;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setPath(string $value);

}
