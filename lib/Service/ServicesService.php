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

use OCA\JMAPC\Store\Local\ServiceEntity;
use OCA\JMAPC\Store\Local\ServicesStore;

class ServicesService {

	private ServicesStore $_Store;

	public function __construct(ServicesStore $ServicesStore) {

		$this->_Store = $ServicesStore;

	}

	/**
	 * @return array<ServiceEntity>
	 */
	public function fetchByUserId(string $uid): array {

		return $this->_Store->fetchByUserId($uid);

	}

	public function fetchByUserIdAndServiceId(string $uid, int $sid): ServiceEntity {

		return $this->_Store->fetchByUserIdAndServiceId($uid, $sid);

	}

	/**
	 * @return array<ServiceEntity>
	 */
	public function fetchByUserIdAndAddress(string $uid, string $address): array {

		return $this->_Store->fetchByUserIdAndAddress($uid, $address);

	}

	public function fetch(int $id): ServiceEntity {

		return $this->_Store->fetch($id);

	}

	public function deposit(string $uid, ServiceEntity $service): ServiceEntity {

		if (!is_numeric($service->getId())) {
			return $this->create($uid, $service);
		} else {
			return $this->modify($uid, $service);
		}
		
	}

	public function create(string $uid, ServiceEntity $service): ServiceEntity {

		$service->setUid($uid);
		return $this->_Store->create($service);

	}

	public function modify(string $uid, ServiceEntity $service): ServiceEntity {

		return $this->_Store->modify($service);

	}

	public function delete(string $uid, ServiceEntity $service): void {

		$this->_Store->delete($service);

	}

}
