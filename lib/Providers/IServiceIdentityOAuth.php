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
interface IServiceIdentityOAuth extends IServiceIdentity {
	/**
	 *
	 * @since 30.0.0
	 */
	public function getAccessToken(): string;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setAccessToken(string $value);

	/**
	 *
	 * @since 30.0.0
	 */
	public function getAccessScope(): array;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setAccessScope(array $value);

	/**
	 *
	 * @since 30.0.0
	 */
	public function getAccessExpiry(): int;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setAccessExpiry(int $value);

	/**
	 *
	 * @since 30.0.0
	 */
	public function getRefreshToken(): string;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setRefreshToken(string $value);

	/**
	 *
	 * @since 30.0.0
	 */
	public function getRefreshLocation(): string;

	/**
	 *
	 * @since 30.0.0
	 */
	public function setRefreshLocation(string $value);

}
