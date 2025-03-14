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

namespace OCA\JMAPC\Service\Local;

use OCA\JMAPC\Store\ContactStore;
use OCA\JMAPC\Store\EventStore;
use OCA\JMAPC\Store\TaskStore;
use OCP\Server;

class LocalService {

	public function __construct (
	) {}

	/**
	 * Initialize local data store client
	 * 
	 * @since Release 1.0.0
	 * 
	 * @return ContactStore
	 */
	public static function initializeContactStore(): ContactStore {
		$store = Server::get(ContactStore::class);
		return $store;
	}

	/**
	 * Initialize local data store client
	 * 
	 * @since Release 1.0.0
	 * 
	 * @return EventStore
	 */
	public static function initializeEventStore(): EventStore {
		$store = Server::get(EventStore::class);
		return $store;
	}

	/**
	 * Initialize local data store client
	 * 
	 * @since Release 1.0.0
	 * 
	 * @return TaskStore
	 */
	public static function initializeTaskStore(): TaskStore {
		$store = Server::get(TaskStore::class);
		return $store;
	}

}
