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
namespace OCA\JMAPC\Service;

use OCA\JMAPC\Store\ServicesStore;
use OCA\JMAPC\Store\ServiceEntity;
use OCP\Security\ICrypto;

class ServicesService {

	private ServicesStore $_Store;
	private ICrypto $_cs;

	/**
	 * Default User Secure Parameters 
	 * @var array
	 * */
	private const _USER_SECURE = [
		'bauth_secret' => true,
		'oauth_access_token' => true,
		'oauth_refresh_token' => true,
	];

	public function __construct(ServicesStore $ServicesStore, ICrypto $crypto) {

		$this->_Store = $ServicesStore;
		$this->_cs = $crypto;

	}

	public function fetchByUserId(string $uid): array {

		// return collection of services/accounts
		return $this->_Store->fetchByUserId($uid);

	}

	public function fetchByUserIdAndServiceId(string $uid, int $sid): array {

		// return collection of services/accounts
		return $this->_Store->fetchByUserIdAndServiceId($uid, $sid);

	}

	public function fetchByUserIdAndAddress(string $uid, string $address): array {

		// return collection of services/accounts
		return $this->_Store->fetchByUserIdAndAddress($uid, $address);

	}

	public function fetch(int $id): ServiceEntity {

		// return service/account information
		return $this->_Store->fetch($id);

	}

	public function deposit(string $uid, ServiceEntity $service): ServiceEntity {

		// create or update service/account information
		if (is_numeric($service->getId())) {
			return $this->_Store->modify($service);
		} else {
			$service->setUid($uid);
			return $this->_Store->create($service);
		}
		
	}

	public function create(array $service): string {

		// return service/account id
		return '8a365c50-0694-4d37-8a40-425f378036ff';

	}

	public function update(string $id, array $service): string {

		// return service/account id
		return '8a365c50-0694-4d37-8a40-425f378036ff';

	}

	public function delete(string $id): string {

		// return service/account id
		return '8a365c50-0694-4d37-8a40-425f378036ff';

	}

}
