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

use OCP\DB\QueryBuilder\IQueryBuilder;
use OC\DB\QueryBuilder\Literal;
use OCP\IDBConnection;

class ServicesStore {

	protected IDBConnection $_Store;
	protected string $_ServicesTable = 'jmapc_services';

	public function __construct(IDBConnection $store) {
		
		$this->_Store = $store;

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
	public function listServices(string $uid): array {
		
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_ServicesTable)
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
	 * delete services for a specific user from data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * 
	 * @return mixed
	 */
	public function deleteServicesByUser(string $uid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ServicesTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * retrieve services from data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param int $id		entity id
	 * 
	 * @return array
	 */
	public function fetchService(int $id): array {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_ServicesTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$data = $cmd->executeQuery()->fetch();
		$cmd->executeQuery()->closeCursor();
		// return result or empty array
		if (is_array($data) && count($data) > 0) {
			return $data;
		}
		else {
			return [];
		}

	}

	/**
	 * confirm service exists in data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $id		entity id
	 * 
	 * @return int|bool			entry id on success / false on failure
	 */
	public function confirmService(string $id): int|bool {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('id')
			->from($this->_ServicesTable)
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
	 * create a service entry in the data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param array $data		entity data
	 * 
	 * @return int				entity id
	 */
	public function createService(array $data) : int {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_ServicesTable);
		foreach ($data as $column => $value) {
			$cmd->setValue($column, $cmd->createNamedParameter($value));
		}
		// execute command
		$cmd->executeStatement();
		// retreive id
		$id = $cmd->getLastInsertId();
		// return result
		return (int) $id;
		
	}
	
	/**
	 * modify a entity entry in the data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param int $id			entity id
	 * @param array $data		entity data
	 * 
	 * @return bool
	 */
	public function modifyService(int $id, array $data) : bool {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->update($this->_ServicesTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		foreach ($data as $column => $value) {
			$cmd->set($column, $cmd->createNamedParameter($value));
		}
		// execute command
		$cmd->executeStatement();
		// return result
		return true;
		
	}

	/**
	 * delete a entity entry from the data store
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param int $id		entity id
	 * 
	 * @return bool
	 */
	public function deleteService(int $id) : bool {

		// retrieve original entity so we can chonicle it later
		$data = $this->fetchEntity($id);
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ServicesTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$cmd->executeStatement();
		// return result
		return true;
		
	}

}
