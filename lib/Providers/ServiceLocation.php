<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
