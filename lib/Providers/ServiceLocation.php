<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers;

//use OCP\Mail\Provider\IServiceLocationUri;
//use OCP\Mail\Provider\IServiceLocation;

class ServiceLocation implements IServiceLocationUri {
	private string $_scheme = 'https://';
	private string $_host = '';
	private string $_path = '/';
	private ?int $_port = null;

	public function __construct(
		string $host = '',
		string $path = '/',
		?int $port = null,
		string $scheme = 'https://',
	) {

		$this->_host = $host;
		$this->_path = $path;
		$this->_port = $port;
		$this->_scheme = $scheme;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function type(): string {

		return 'URI';
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function location(): string {

		if (isset($this->_port)) {
			return $this->_scheme . $this->_host . ':' . $this->_port . $this->_path;
		} else {
			return $this->_scheme . $this->_host . $this->_path;
		}

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getScheme(): string {

		return $this->_scheme;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setScheme(string $value) {

		$this->_scheme = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getHost(): string {

		return $this->_host;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setHost(string $value) {

		$this->_host = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getPort(): int {

		return $this->_port;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setPort(int $value) {

		$this->_port = $value;

	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getPath(): string {

		return $this->_path;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setPath(string $value) {

		$this->_path = $value;

	}

}
