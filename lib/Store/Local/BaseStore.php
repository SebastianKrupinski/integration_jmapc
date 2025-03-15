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

namespace OCA\JMAPC\Store\Local;

use OC\DB\QueryBuilder\Literal;
use OCA\JMAPC\Store\Common\Range\IRange;
use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;

class BaseStore {

	protected IDBConnection $_Store;
	protected string $_CollectionTable = '';
	protected string $_CollectionIdentifier = '';
	protected string $_CollectionClass = '';
	protected string $_EntityTable = '';
	protected string $_EntityIdentifier = '';
	protected string $_EntityClass = '';
	protected string $_ChronicleTable = '';

	protected function toCollection(array $row): Entity {
		unset($row['DOCTRINE_ROWNUM']); // remove doctrine/dbal helper column
		return \call_user_func($this->_CollectionClass . '::fromRow', $row);
	}

	protected function toEntity(array $row): Entity {
		unset($row['DOCTRINE_ROWNUM']); // remove doctrine/dbal helper column
		return \call_user_func($this->_EntityClass . '::fromRow', $row);
	}

	/**
	 * retrieve collections from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @return array<int, CollectionEntity>
	 */
	public function collectionList(): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)));
		// execute command
		$rsl = $cmd->executeQuery();
		$entities = [];
		try {
			while ($data = $rsl->fetch()) {
				$entities[] = $this->toCollection($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}
	}

	/**
	 * retrieve collections for specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 *
	 * @return array<int, CollectionEntity>
	 */
	public function collectionListByUser(string $uid): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)))
			->andWhere($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		// execute command
		$rsl = $cmd->executeQuery();
		$entities = [];
		try {
			while ($data = $rsl->fetch()) {
				$entities[] = $this->toCollection($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * retrieve collections for specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $sid service id
	 *
	 * @return array<int, CollectionEntity>
	 */
	public function collectionListByService(int $sid): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)))
			->andWhere($cmd->expr()->eq('sid', $cmd->createNamedParameter($sid)));
		// execute command
		$rsl = $cmd->executeQuery();
		$entities = [];
		try {
			while ($data = $rsl->fetch()) {
				$entities[] = $this->toCollection($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}

	}

	/*
	 * confirm collection exists in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id			collection id
	 *
	 * @return int|bool			collection id on success / false on failure
	 */
	public function collectionConfirm(int $cid): int|bool {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('id')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($cid)));
		// execute command
		$data = $cmd->executeQuery()->fetch();
		$cmd->executeQuery()->closeCursor();
		// evaluate if anything was found
		if (is_array($data) && count($data) > 0) {
			return (int)$data['id'];
		} else {
			return false;
		}

	}

	/**
	 * confirm collection exists in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param string $uuid collection uuid
	 *
	 * @return int|bool collection id on success / false on failure
	 */
	public function collectionConfirmByUUID(string $uid, string $uuid): int|bool {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('id')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)))
			->andWhere($cmd->expr()->eq('uuid', $cmd->createNamedParameter($uuid)));
		// execute command
		$data = $cmd->executeQuery()->fetch();
		$cmd->executeQuery()->closeCursor();
		// evaluate if anything was found
		if (is_array($data) && count($data) > 0) {
			return (int)$data['id'];
		} else {
			return false;
		}

	}

	/**
	 * retrieve collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id collection id
	 *
	 * @return CollectionEntity
	 */
	public function collectionFetch(int $id): CollectionEntity {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			return $this->toCollection($rsl->fetch());
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * retrieve collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param string $uuid collection uuid
	 *
	 * @return CollectionEntity
	 */
	public function collectionFetchByUUID(string $uid, string $uuid): CollectionEntity {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)))
			->andWhere($cmd->expr()->eq('uuid', $cmd->createNamedParameter($uuid)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			return $this->toCollection($rsl->fetch());
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * fresh instance of a collection entity
	 *
	 * @since Release 1.0.0
	 *
	 * @return CollectionEntity
	 */
	public function collectionFresh(): CollectionEntity {

		return new $this->_CollectionClass;
		
	}

	/**
	 * create a collection entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param CollectionEntity $entity
	 *
	 * @return CollectionEntity
	 */
	public function collectionCreate(CollectionEntity $entity): CollectionEntity {

		// force type
		$entity->setType($this->_CollectionIdentifier);
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_CollectionTable);
		// assign values
		foreach (array_keys($entity->getUpdatedFields()) as $property) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);
			$value = $entity->$getter();
			$cmd->setValue($column, $cmd->createNamedParameter($value));
		}
		// execute command
		$cmd->executeStatement();
		// determine if id needs to be assigned
		if ($entity->id === null) {
			$entity->setId($cmd->getLastInsertId());
		}
		
		$entity->resetUpdatedFields();

		return $entity;
		
	}
	
	/**
	 * modify a collection entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param CollectionEntity $entity
	 *
	 * @return CollectionEntity
	 */
	public function collectionModify(CollectionEntity $entity): CollectionEntity {

		// force type
		$entity->setType($this->_CollectionIdentifier);
		// construct command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->update($this->_CollectionTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// assign values
		if (count($entity->getUpdatedFields())) {
			foreach (array_keys($entity->getUpdatedFields()) as $property) {
				$column = $entity->propertyToColumn($property);
				$getter = 'get' . ucfirst($property);
				$value = $entity->$getter();
				$cmd->set($column, $cmd->createNamedParameter($value));
			}
			// execute command
			$cmd->executeStatement();
			// determine if id needs to be assigned
			if ($entity->id === null) {
				$entity->setId($cmd->getLastInsertId());
			}
		}

		$entity->resetUpdatedFields();

		return $entity;

	}

	/**
	 * delete a collection entry from the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param CollectionEntity $entity
	 *
	 * @return CollectionEntity
	 */
	public function collectionDelete(CollectionEntity $entity): CollectionEntity {

		// remove entities
		$this->entityDeleteByCollection($entity->getId());
		// remove chronicle
		$this->chronicleExpungeByCollection($entity->getId());
		// remove collection
		// construct command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// execute command
		$cmd->executeStatement();
		
		return $entity;
		
	}

	/**
	 * delete collections for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id collection id
	 *
	 * @return mixed
	 */
	public function collectionDeleteById(int $id): mixed {

		// remove entities
		$this->entityDeleteByCollection($id);
		// remove chronicle
		$this->chronicleExpungeByCollection($id);
		// remove collection
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete collections for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $id user id
	 *
	 * @return mixed
	 */
	public function collectionDeleteByUser(string $id): mixed {

		// remove entities
		$this->entityDeleteByUser($id);
		// remove chronicle
		$this->chronicleExpungeByUser($id);
		// remove collection
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($id)))
			->andWhere($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete collections for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id service id
	 *
	 * @return mixed
	 */
	public function collectionDeleteByService(int $id): mixed {

		// remove entities
		$this->entityDeleteByService($id);
		// remove chronicle
		$this->chronicleExpungeByService($id);
		// remove collection
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('sid', $cmd->createNamedParameter($id)))
			->andWhere($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * retrieve entities for specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 *
	 * @return array of entities
	 */
	public function entityList(string $uid): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		// execute command
		$rsl = $cmd->executeQuery();
		$entities = [];
		try {
			while ($data = $rsl->fetch()) {
				$entities[] = $this->toEntity($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * retrieve entities for specific user and collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 *
	 * @return array of entities
	 */
	public function entityListByCollection(int $cid): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)));
		// execute command
		$rsl = $cmd->executeQuery();
		$entities = [];
		try {
			while ($data = $rsl->fetch()) {
				$entities[] = $this->toEntity($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * confirm entity exists in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id entity id
	 *
	 * @return int|bool entry id on success / false on failure
	 */
	public function entityConfirm(int $id): int|bool {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('id')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$data = $cmd->executeQuery()->fetch();
		$cmd->executeQuery()->closeCursor();
		// evaluate if anything was found
		if (is_array($data) && count($data) > 0) {
			return (int)$data['id'];
		} else {
			return false;
		}

	}

	/**
	 * check if entity exists in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $uuid entity uuid
	 *
	 * @return int|bool entry id on success / false on failure
	 */
	public function entityConfirmByUUID(int $cid, string $uuid): int|bool {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('id')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('uuid', $cmd->createNamedParameter($uuid)));
		// execute command
		$data = $cmd->executeQuery()->fetch();
		$cmd->executeQuery()->closeCursor();
		// evaluate if anything was found
		if (is_array($data) && count($data) > 0) {
			return (int)$data['id'];
		} else {
			return false;
		}

	}

	/**
	 * retrieve entities for specific user, collection and search parameters from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param array $filter filter options
	 * @param array $elements data fields
	 *
	 * @return array of entities
	 */
	public function entityFind(string $cid, array $elements = [], ?array $filter = null, ?IRange $range = null, $sort): array {
		
		// evaluate if specific elements where requested
		if (!is_array($elements)) {
			$elements = ['*'];
		}
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select($elements)
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)));
		
		foreach ($filter as $entry) {
			if (is_array($entry) && count($entry) == 3) {
				switch ($entry[1]) {
					case '=':
						$cmd->andWhere($cmd->expr()->eq($entry[0], $cmd->createNamedParameter($entry[2])));
						break;
					case '!=':
						$cmd->andWhere($cmd->expr()->neq($entry[0], $cmd->createNamedParameter($entry[2])));
						break;
					case '>':
						$cmd->andWhere($cmd->expr()->gt($entry[0], $cmd->createNamedParameter($entry[2])));
						break;
					case '>=':
						$cmd->andWhere($cmd->expr()->gte($entry[0], $cmd->createNamedParameter($entry[2])));
						break;
					case '<':
						$cmd->andWhere($cmd->expr()->lt($entry[0], $cmd->createNamedParameter($entry[2])));
						break;
					case '<=':
						$cmd->andWhere($cmd->expr()->lte($entry[0], $cmd->createNamedParameter($entry[2])));
						break;
				}
			}
		}
		// execute command
		$rsl = $cmd->executeQuery();
		$entities = [];
		try {
			while ($data = $rsl->fetch()) {
				$entities[] = $this->toEntity($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * retrieve entity from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id entity id
	 *
	 * @return Entity|null
	 */
	public function entityFetch(int $id): ?Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			$entity = $rsl->fetch();
			if (is_array($entity)) {
				return $this->toEntity($entity);
			} else {
				return null;
			}
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * retrieve entity from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $uuid entity uuid
	 *
	 * @return Entity|null
	 */
	public function entityFetchByUUID(int $cid, string $uuid): ?Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('uuid', $cmd->createNamedParameter($uuid)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			$entity = $rsl->fetch();
			if ($entity) {
				return $this->toEntity($entity);
			} else {
				return null;
			}
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * retrieve entity from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $ccid correlation collection id
	 * @param string $ceid correlation entity id
	 *
	 * @return Entity|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ?Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('ccid', $cmd->createNamedParameter($ccid)))
			->andWhere($cmd->expr()->eq('ceid', $cmd->createNamedParameter($ceid)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			$entity = $rsl->fetch();
			if ($entity) {
				return $this->toEntity($entity);
			} else {
				return null;
			}
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * fresh instance of a entity
	 *
	 * @since Release 1.0.0
	 *
	 * @return Entity
	 */
	public function entityFresh(): Entity {

		return new $this->_EntityClass;
		
	}

	/**
	 * create a entity entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	public function entityCreate(Entity $entity): Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_EntityTable);
		// assign values
		foreach (array_keys($entity->getUpdatedFields()) as $property) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);
			$value = $entity->$getter();
			$cmd->setValue($column, $cmd->createNamedParameter($value));
		}
		// execute command
		$cmd->executeStatement();
		// determine if id needs to be assigned
		if ($entity->id === null) {
			$entity->setId($cmd->getLastInsertId());
		}
		// chronicle operation
		$this->chronicleDocument($entity->getUid(), $entity->getSid(), $entity->getCid(), $entity->getId(), $entity->getUuid(), 1);

		$entity->resetUpdatedFields();

		return $entity;
		
	}
	
	/**
	 * modify a entity entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	public function entityModify(Entity $entity): Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->update($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// assign values
		if (count($entity->getUpdatedFields())) {
			foreach (array_keys($entity->getUpdatedFields()) as $property) {
				$column = $entity->propertyToColumn($property);
				$getter = 'get' . ucfirst($property);
				$value = $entity->$getter();
				$cmd->set($column, $cmd->createNamedParameter($value));
			}
			// execute command
			$cmd->executeStatement();
			// determine if id needs to be assigned
			if ($entity->id === null) {
				$entity->setId($cmd->getLastInsertId());
			}
		}
		// chronicle operation
		$this->chronicleDocument($entity->getUid(), $entity->getSid(), $entity->getCid(), $entity->getId(), $entity->getUuid(), 2);
		
		$entity->resetUpdatedFields();
		
		return $entity;
		
	}

	/**
	 * delete a entity from the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	public function entityDelete(Entity $entity): Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// execute command
		$cmd->executeStatement();
		// chronicle operation
		$this->chronicleDocument($entity->getUid(), $entity->getSid(), $entity->getCid(), $entity->getId(), $entity->getUuid(), 3);
		// return result
		return $entity;
		
	}

	/**
	 * delete entity by id
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $id entity id
	 *
	 * @return mixed
	 */
	public function entityDeleteById(int $id): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete entities for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 *
	 * @return mixed
	 */
	public function entityDeleteByUser(string $uid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete entities for a specific service from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $sid service id
	 *
	 * @return mixed
	 */
	public function entityDeleteByService(int $sid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('sid', $cmd->createNamedParameter($sid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete entities for a specific collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 *
	 * @return mixed
	 */
	public function entityDeleteByCollection(int $cid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * chronicle a operation to an entity to the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param string $cid collection id
	 * @param string $eid entity id
	 * @param string $euuid entity uuid
	 * @param string $operation operation type (1 - Created, 2 - Modified, 3 - Deleted)
	 *
	 * @return string
	 */
	public function chronicleDocument(string $uid, int $sid, int $cid, int $eid, string $euuid, int $operation): string {

		// capture current microtime
		$stamp = microtime(true);
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_ChronicleTable);
		$cmd->setValue('uid', $cmd->createNamedParameter($uid));
		$cmd->setValue('sid', $cmd->createNamedParameter($sid));
		$cmd->setValue('tag', $cmd->createNamedParameter($this->_EntityIdentifier));
		$cmd->setValue('cid', $cmd->createNamedParameter($cid));
		$cmd->setValue('eid', $cmd->createNamedParameter($eid));
		$cmd->setValue('euuid', $cmd->createNamedParameter($euuid));
		$cmd->setValue('operation', $cmd->createNamedParameter($operation));
		$cmd->setValue('stamp', $cmd->createNamedParameter($stamp));
		// execute command
		$cmd->executeStatement();
		// return stamp
		return base64_encode((string)$stamp);
		
	}

	/**
	 * reminisce operations to entities in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param int $encode weather to encode the result
	 *
	 * @return int|float|string
	 */
	public function chronicleApex(int $cid, bool $encode = true): int|float|string {

		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select($cmd->func()->max('stamp'))
			->from($this->_ChronicleTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('tag', $cmd->createNamedParameter($this->_EntityIdentifier)));
		$stampApex = $cmd->executeQuery()->fetchOne();
		$cmd->executeQuery()->closeCursor();

		if ($encode) {
			return base64_encode((string)max(0, $stampApex));
		} else {
			return max(0, $stampApex);
		}
		
	}

	/**
	 * reminisce operations to entities in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $stamp time stamp
	 * @param int $limit results limit
	 * @param int $offset results offset
	 *
	 * @return array
	 */
	public function chronicleReminisce(int $cid, string $stamp, ?int $limit = null, ?int $offset = null): array {

		// retrieve apex stamp
		$stampApex = $this->chronicleApex($cid, false);
		// determine nadir stamp
		$stampNadir = !empty($stamp) ? base64_decode($stamp) : '';
		$initial = !is_numeric($stampNadir);

		// retrieve additions
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('eid', 'euuid', new Literal('MAX(operation) AS operation'))
			->from($this->_ChronicleTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('tag', $cmd->createNamedParameter($this->_EntityIdentifier)))
			->groupBy('eid');
		// evaluate if this is a initial reconciliation
		if ($initial) {
			// select only entries that are not deleted
			$cmd->having(new Literal('MAX(operation) != 3'));
		} else {
			// select entries between nadir and apex
			$cmd->andWhere($cmd->expr()->gt('stamp', $cmd->createNamedParameter($stampNadir)));
			$cmd->andWhere($cmd->expr()->lte('stamp', $cmd->createNamedParameter($stampApex)));
		}
		// evaluate if limit exists
		if (is_numeric($limit)) {
			$cmd->setMaxResults($limit);
		}
		// evaluate if offset exists
		if (is_numeric($offset)) {
			$cmd->setFirstResult($offset);
		}

		// define place holder
		$chronicle = ['additions' => [], 'modifications' => [], 'deletions' => [], 'stamp' => base64_encode((string)$stampApex)];
		
		// execute command
		$rs = $cmd->executeQuery();
		// process result
		while (($entry = $rs->fetch()) !== false) {
			switch ($entry['operation']) {
				case $initial:
				case 1:
					$chronicle['additions'][] = ['id' => $entry['eid'], 'uuid' => $entry['euuid']];
					break;
				case 2:
					$chronicle['modifications'][] = ['id' => $entry['eid'], 'uuid' => $entry['euuid']];
					break;
				case 3:
					$chronicle['deletions'][] = ['id' => $entry['eid'], 'uuid' => $entry['euuid']];
					break;
			}
		}
		$rs->closeCursor();

		// return stamp
		return $chronicle;
		
	}

	/**
	 * delete chronicle entries for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id user id
	 *
	 * @return mixed
	 */
	public function chronicleExpungeByUser(string $id) {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ChronicleTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();
	}

	/**
	 * delete chronicle entries for a specific service from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id service id
	 *
	 * @return mixed
	 */
	public function chronicleExpungeByService(int $id) {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ChronicleTable)
			->where($cmd->expr()->eq('sid', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();
	}

	/**
	 * delete chronicle entries for a specific collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id collection id
	 *
	 * @return mixed
	 */
	public function chronicleExpungeByCollection(int $id) {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ChronicleTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();
	}

}
