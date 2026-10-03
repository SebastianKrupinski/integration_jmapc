<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers;

//use OCP\Mail\Provider\IServiceIdentity;
//use OCP\Mail\Provider\IServiceIdentityOAuth;

class ServiceIdentityOAuth implements IServiceIdentityOAuth {
	private string $_AccessId = '';
	private string $_AccessToken = '';
	private array $_AccessScope = [];
	private int $_AccessExpiry = 3600;
	private string $_RefreshToken = '';
	private string $_RefreshLocation = '';

	public function __construct(
		string $id = '',
		string $access = '',
		int $expiry = 0,
		string $refresh = '',
	) {

		$this->_AccessId = $id;
		$this->_AccessToken = $access;
		$this->_AccessExpiry = $expiry;
		$this->_RefreshToken = $refresh;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function type(): string {

		return 'OAUTH';
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function label(): string {

		return 'Bearer Authentication';
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getAccessId(): string {

		return $this->_AccessId;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setAccessId(string $value) {

		$this->_AccessId = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getAccessToken(): string {

		return $this->_AccessToken;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setAccessToken(string $value) {

		$this->_AccessToken = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getAccessScope(): array {

		return $this->_AccessScope;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setAccessScope(array $value) {

		$this->_AccessScope = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getAccessExpiry(): int {

		return $this->_AccessExpiry;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setAccessExpiry(int $value) {

		$this->_AccessExpiry = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getRefreshToken(): string {

		return $this->_RefreshToken;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setRefreshToken(string $value) {

		$this->_RefreshToken = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getRefreshLocation(): string {

		return $this->_RefreshLocation;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setRefreshLocation(string $value) {

		$this->_RefreshLocation = $value;

	}

}
