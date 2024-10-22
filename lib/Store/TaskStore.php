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

namespace OCA\JMAPC\Store;

use OCA\JMAPC\Store\CollectionEntity;
use OCA\JMAPC\Store\TaskEntity;
use OCP\IDBConnection;

class TaskStore extends BaseStore {

	public function __construct(IDBConnection $store) {
		
		$this->_Store = $store;
		$this->_CollectionTable = 'jmapc_collections';
		$this->_CollectionIdentifier = 'TC';
		$this->_CollectionClass = 'OCA\JMAPC\Store\CollectionEntity';
		$this->_EntityTable = 'jmapc_entities_task';
		$this->_EntityIdentifier = 'TE';
		$this->_EntityClass = 'OCA\JMAPC\Store\TaskEntity';
		$this->_ChronicleTable = 'jmapc_chronicle';

	}

}