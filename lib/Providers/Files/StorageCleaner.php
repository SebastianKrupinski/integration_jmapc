<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Files;

use OC\Files\Cache\Storage;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Removes the cached metadata of a storage that is no longer mounted
 */
class StorageCleaner {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function remove(string $storageId): void {
		$info = Storage::getStorageById($storageId);
		if ($info === null) {
			return;
		}
		$numericId = (int)$info['numeric_id'];
		Storage::removeFileCacheEntries($numericId);

		$cmd = $this->db->getQueryBuilder();
		$cmd->delete('mounts')
			->where($cmd->expr()->eq('storage_id', $cmd->createNamedParameter($numericId, IQueryBuilder::PARAM_INT)))
			->executeStatement();

		$cmd = $this->db->getQueryBuilder();
		$cmd->delete('storages')
			->where($cmd->expr()->eq('numeric_id', $cmd->createNamedParameter($numericId, IQueryBuilder::PARAM_INT)))
			->executeStatement();

		Storage::getGlobalCache()->clearCache();
	}

}
