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

//use OCP\Mail\Provider\IServiceIdentity;
//use OCP\Mail\Provider\IServiceIdentityOAuth;

class ServiceIdentityOAuth implements IServiceIdentityOAuth {

	private string $_AccessToken = '';
	private array $_AccessScope = [];
	private string $_AccessExpiry = '';
	private string $_RefreshToken = '';
	private string $_RefreshLocation = '';

	public function __construct(
		string $access = '',
		int $expiry = 0,
		string $refresh = ''
	) {

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
