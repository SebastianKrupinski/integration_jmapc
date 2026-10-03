<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers;

//use OCP\Mail\Provider\IServiceIdentity;
//use OCP\Mail\Provider\IServiceIdentityBAuth;

class ServiceIdentityBAuth implements IServiceIdentityBAuth {
	private string $_identity = '';
	private string $_secret = '';

	public function __construct(
		string $identity = '',
		string $secret = '',
	) {

		$this->_identity = $identity;
		$this->_secret = $secret;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function type(): string {

		return 'BAUTH';
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function label(): string {

		return 'Basic Authentication';
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getIdentity(): string {

		return $this->_identity;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setIdentity(string $value) {

		$this->_identity = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getSecret(): string {

		return $this->_secret;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setSecret(string $value) {

		$this->_secret = $value;

	}

}
