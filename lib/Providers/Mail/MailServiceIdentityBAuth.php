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
namespace OCA\JMAPC\Providers\Mail;

use OCP\Mail\Provider\IServiceIdentityBAuth;

use OCA\JMAPC\AppInfo\Application;

class MailServiceIdentityBAuth implements IServiceIdentityBAuth {

	private string $_identity = '';
	private string $_secret = '';

	public function __construct(
		string $identity = '',
		string $secret = ''
	) {

		$this->_identity = $identity;
		$this->_secret = $secret;

	}

	/**
	 * 
	 * @since 1.0.0
	 */
	public function id(): string {
		
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
