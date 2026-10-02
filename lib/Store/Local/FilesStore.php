<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Local;

use OCA\JMAPC\Store\Common\Filters\FilterComparisonOperator;
use OCA\JMAPC\Store\Common\Filters\FilterConjunctionOperator;
use OCA\JMAPC\Store\Common\Filters\IFilter;
use OCA\JMAPC\Store\Common\Sort\ISort;
use OCA\JMAPC\Store\Common\Sort\SortBase;
use OCA\JMAPC\Store\Local\Filters\FileCollectionFilter;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FileCollectionEntity>
 */
class FilesStore extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'jmapc_collections_file', FileCollectionEntity::class);
	}

	protected function fromFilter(IQueryBuilder $cmd, IFilter $filter): void {
		foreach ($filter->conditions() as $entry) {
			$comparison = match ($entry['comparator']) {
				FilterComparisonOperator::EQ => $cmd->expr()->eq($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::GT => $cmd->expr()->gt($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::LT => $cmd->expr()->lt($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::GTE => $cmd->expr()->gte($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::LTE => $cmd->expr()->lte($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::NEQ => $cmd->expr()->neq($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::IN => $cmd->expr()->in($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::NIN => $cmd->expr()->notIn($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::LIKE => $cmd->expr()->like($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::NLIKE => $cmd->expr()->notLike($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
			};
			if ($entry['conjunction'] === FilterConjunctionOperator::OR) {
				$cmd->orWhere($comparison);
			} else {
				$cmd->andWhere($comparison);
			}
		}
	}

	protected function fromSort(IQueryBuilder $cmd, ISort $sort): void {
		foreach ($sort->conditions() as $entry) {
			$cmd->addOrderBy($entry['attribute'], $entry['direction'] ? 'ASC' : 'DESC');
		}
	}

	/**
	 * retrieve collections from data store
	 *
	 * @param IFilter $filter filter options
	 * @param ISort $sort sort options
	 *
	 * @return array<int,FileCollectionEntity> collections indexed by id
	 */
	public function collectionList(?IFilter $filter = null, ?ISort $sort = null): array {
		$cmd = $this->db->getQueryBuilder();
		$cmd->select('*')
			->from($this->getTableName());
		if ($filter instanceof IFilter) {
			$this->fromFilter($cmd, $filter);
		}
		if ($sort instanceof ISort) {
			$this->fromSort($cmd, $sort);
		}
		return $this->indexed($this->findEntities($cmd));
	}

	public function collectionListFilter(): IFilter {
		return new FileCollectionFilter();
	}

	public function collectionListSort(): ISort {
		return new SortBase();
	}

	/**
	 * retrieve the visible collections of a user in a mode, whose service is enabled and connected
	 *
	 * @param string $uid user id
	 * @param string $mode collection mode
	 *
	 * @return array<int,FileCollectionEntity> collections indexed by id
	 */
	public function collectionListActive(string $uid, string $mode): array {
		$cmd = $this->db->getQueryBuilder();
		$cmd->select('c.*')
			->from($this->getTableName(), 'c')
			->innerJoin('c', 'jmapc_services', 's', $cmd->expr()->eq('c.sid', 's.id'))
			->where($cmd->expr()->eq('c.uid', $cmd->createNamedParameter($uid)))
			->andWhere($cmd->expr()->eq('c.mode', $cmd->createNamedParameter($mode)))
			->andWhere($cmd->expr()->eq('c.visible', $cmd->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($cmd->expr()->eq('s.enabled', $cmd->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($cmd->expr()->eq('s.connected', $cmd->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		return $this->indexed($this->findEntities($cmd));
	}

	/**
	 * retrieve collection from data store
	 *
	 * @param int $id collection id
	 */
	public function collectionFetch(int $id): ?FileCollectionEntity {
		$cmd = $this->db->getQueryBuilder();
		$cmd->select('*')
			->from($this->getTableName())
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		try {
			return $this->findEntity($cmd);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function collectionCreate(FileCollectionEntity $entity): FileCollectionEntity {
		return $this->insert($entity);
	}

	public function collectionModify(FileCollectionEntity $entity): FileCollectionEntity {
		return $this->update($entity);
	}

	public function collectionDelete(FileCollectionEntity $entity): FileCollectionEntity {
		return $this->delete($entity);
	}

	/**
	 * delete all collections of a service from the data store
	 *
	 * @param int $sid service id
	 *
	 * @return int number of deleted collections
	 */
	public function collectionDeleteByService(int $sid): int {
		$cmd = $this->db->getQueryBuilder();
		$cmd->delete($this->getTableName())
			->where($cmd->expr()->eq('sid', $cmd->createNamedParameter($sid, IQueryBuilder::PARAM_INT)));
		return $cmd->executeStatement();
	}

	/**
	 * delete all collections of a user from the data store
	 *
	 * @param string $uid user id
	 *
	 * @return int number of deleted collections
	 */
	public function collectionDeleteByUser(string $uid): int {
		$cmd = $this->db->getQueryBuilder();
		$cmd->delete($this->getTableName())
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		return $cmd->executeStatement();
	}

	/**
	 * @param list<FileCollectionEntity> $entities
	 *
	 * @return array<int,FileCollectionEntity>
	 */
	private function indexed(array $entities): array {
		$indexed = [];
		foreach ($entities as $entity) {
			$indexed[$entity->getId()] = $entity;
		}
		return $indexed;
	}

}
