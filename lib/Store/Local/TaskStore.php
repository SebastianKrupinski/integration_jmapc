<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Local;

use OCP\IDBConnection;

class TaskStore extends BaseStore {

	public function __construct(IDBConnection $store) {

		$this->_Store = $store;
		$this->_CollectionTable = 'jmapc_collections';
		$this->_CollectionIdentifier = 'TC';
		$this->_CollectionClass = 'OCA\JMAPC\Store\Local\CollectionEntity';
		$this->_EntityTable = 'jmapc_entities_task';
		$this->_EntityIdentifier = 'TE';
		$this->_EntityClass = 'OCA\JMAPC\Store\Local\TaskEntity';
		$this->_ChronicleTable = 'jmapc_chronicle';

	}

}
