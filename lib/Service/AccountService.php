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

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\ServicesStore;

class AccountService {

	private ServicesStore $_Store;

	public function __construct(ServicesStore $ServicesStore) {

		$this->_Store = $ServicesStore;

	}

	public function fetchAny(string $uid): array {

		// return collection of services/accounts
		return $this->_Store->listServices($uid);

	}

	public function fetch(string $id): array {


		// return service/account information
		return [];

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
