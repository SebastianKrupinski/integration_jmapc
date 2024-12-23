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

use OCA\JMAPC\Store\ServiceEntity;
use OCP\IDBConnection;

class ServicesStore {

	protected IDBConnection $_Store;
	protected string $_EntityTable = 'jmapc_services';
	protected string $_EntityClass = 'OCA\JMAPC\Store\ServiceEntity';

	public function __construct(IDBConnection $store) {
		$this->_Store = $store;
	}

	protected function toEntity(array $row): ServiceEntity {
		unset($row['DOCTRINE_ROWNUM']); // remove doctrine/dbal helper column
		return \call_user_func($this->_EntityClass .'::fromRow', $row);
	}

	/**
	 * retrieve services for specific user from data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * 
	 * @return array 			of services
	 */
	public function fetchByUserId(string $uid): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));

		// execute command
		$rs = $cmd->executeQuery()->fetchAll();
		$cmd->executeQuery()->closeCursor();
		// return result or null
		if (is_array($rs) && count($rs) > 0) {
			return $rs;
		}
		else {
			return [];
		}

	}

	/**
	 * retrieve services for specific user from data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * @param string $sid		service id
	 * 
	 * @return ServiceEntity|null
	 */
	public function fetchByUserIdAndServiceId(string $uid, int $sid): ServiceEntity|null {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)))
			->andWhere($cmd->expr()->eq('id', $cmd->createNamedParameter($sid)));
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
	 * retrieve services for specific user from data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * 
	 * @return array 			of services
	 */
	public function fetchByUserIdAndAddress(string $uid, string $address): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)))
			->andWhere($cmd->expr()->eq('address_primary', $cmd->createNamedParameter($address)));

		// execute command
		$rs = $cmd->executeQuery()->fetchAll();
		$cmd->executeQuery()->closeCursor();
		// return result or null
		if (is_array($rs) && count($rs) > 0) {
			return $rs;
		}
		else {
			return [];
		}

	}

	/**
	 * confirm entity exists in data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param int $id			entity id
	 * 
	 * @return int|false		entry id on success / false on failure
	 */
	public function confirm(int $id): int|false {

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
			return (int) $data['id'];
		}
		else {
			return false;
		}

	}

	/**
	 * retrieve entity from data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param int $id		entity id
	 * 
	 * @return ServiceEntity|null
	 */
	public function fetch(int $id): ServiceEntity|null {

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
	 * create a entity entry in the data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param ServiceEntity $entity
	 * 
	 * @return ServiceEntity
	 */
	public function create(ServiceEntity $entity): ServiceEntity {

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

		$entity->resetUpdatedFields();

		return $entity;
		
	}
	
	/**
	 * modify a entity entry in the data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param ServiceEntity $entity
	 * 
	 * @return ServiceEntity
	 */
	public function modify(ServiceEntity $entity): ServiceEntity {

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

		$entity->resetUpdatedFields();
		
		return $entity;
		
	}

	/**
	 * delete a entity from the data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param ServiceEntity $entity
	 * 
	 * @return ServiceEntity
	 */
	public function delete(ServiceEntity $entity): ServiceEntity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// execute command
		$cmd->executeStatement();

		// return result
		return $entity;
		
	}

	/**
	 * delete services for a specific user from data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * 
	 * @return mixed
	 */
	public function deleteByUser(string $uid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

}
