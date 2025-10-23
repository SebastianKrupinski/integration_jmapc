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

namespace OCA\JMAPC\Service\Remote;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

use JmapClient\Client;

use JmapClient\Requests\Tasks\TaskChanges;
use JmapClient\Requests\Tasks\TaskGet;
use JmapClient\Requests\Tasks\TaskListGet;
use JmapClient\Requests\Tasks\TaskListSet;
use JmapClient\Requests\Tasks\TaskParameters as TaskParametersRequest;
use JmapClient\Requests\Tasks\TaskQuery;
use JmapClient\Requests\Tasks\TaskQueryChanges;
use JmapClient\Requests\Tasks\TaskSet;
use JmapClient\Responses\ResponseException;
use JmapClient\Responses\Tasks\TaskListParameters as TaskListParametersResponse;
use JmapClient\Responses\Tasks\TaskParameters as TaskParametersResponse;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\BaseStringCollection;
use OCA\JMAPC\Objects\DeltaObject;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Objects\Task\TaskCollectionObject;
use OCA\JMAPC\Objects\Task\TaskObject;
use OCA\JMAPC\Store\Common\Filters\IFilter;
use OCA\JMAPC\Store\Common\Range\IRangeTally;
use OCA\JMAPC\Store\Common\Sort\ISort;

class RemoteTasksService {
	public ?DateTimeZone $SystemTimeZone = null;
	public ?DateTimeZone $UserTimeZone = null;

	protected Client $dataStore;
	protected string $dataAccount;

	protected ?string $resourceNamespace = null;
	protected ?string $resourceCollectionLabel = null;
	protected ?string $resourceEntityLabel = null;

	protected array $collectionPropertiesDefault = [];
	protected array $collectionPropertiesBasic = [];
	protected array $entityPropertiesDefault = [];
	protected array $entityPropertiesBasic = [
		'id', 'calendarIds', 'uid', 'created', 'updated'
	];

	public function __construct() {
	}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
		// evaluate if client is connected
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
		// determine account
		if ($dataAccount === null) {
			if ($this->resourceNamespace !== null) {
				$this->dataAccount = $dataStore->sessionAccountDefault($this->resourceNamespace, false);
			} else {
				$this->dataAccount = (string)$dataStore->sessionAccountDefault('tasks');
			}
		} else {
			$this->dataAccount = $dataAccount;
		}

	}

	/**
	 * retrieve properties for specific collection
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionFetch(string $id): ?TaskCollectionObject {
		// construct request
		$r0 = new TaskListGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		if (!empty($id)) {
			$r0->target($id);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to collection object
		if ($response->object(0) instanceof TaskListParametersResponse) {
			$co = $response->object(0);
			$collection = new TaskCollectionObject();
			$collection->Id = $co->id();
			$collection->Label = $co->label();
			$collection->Description = $co->description();
			$collection->Priority = $co->priority();
			$collection->Visibility = $co->visible();
			$collection->Color = $co->color();
			return $collection;
		} else {
			return null;
		}
	}

	/**
	 * create collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionCreate(TaskCollectionObject $collection): string {
		// construct request
		$r0 = new TaskListSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$m0 = $r0->create('1');
		if ($collection->Label) {
			$m0->label($collection->Label);
		}
		if ($collection->Description) {
			$m0->description($collection->Description);
		}
		if ($collection->Priority) {
			$m0->priority($collection->Priority);
		}
		if ($collection->Visibility) {
			$m0->visible($collection->Visibility);
		}
		if ($collection->Color) {
			$m0->color($collection->Color);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return (string)$response->created()['1']['id'];
	}

	/**
	 * update collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionUpdate(string $id, TaskCollectionObject $collection): string {
		// construct request
		$r0 = new TaskListSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$m0 = $r0->update($id);
		$m0->label($collection->Label);
		$m0->description($collection->Description);
		$m0->priority($collection->Priority);
		$m0->visible($collection->Visibility);
		$m0->color($collection->Color);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return array_key_exists($id, $response->updated()) ? (string)$id : '';
	}

	/**
	 * delete collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionDelete(string $id): string {
		// construct request
		$r0 = new TaskListSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->delete($id);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return (string)$response->deleted()[0];
	}

	/**
	 * list of collections in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string|null $location Id of parent collection
	 * @param string|null $granularity Amount of detail to return
	 * @param int|null $depth Depth of sub collections to return
	 *
	 * @return array<string,TaskCollectionObject>
	 */
	public function collectionList(?string $location = null, ?string $granularity = null, ?int $depth = null): array {
		// construct request
		$r0 = new TaskListGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		// set target to query request
		if ($location !== null) {
			$r0->target($location);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command errored
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap objects to collection objects
		$list = [];
		foreach ($response->objects() as $co) {
			$collection = new TaskCollectionObject();
			$collection->Id = $co->id();
			$collection->Label = $co->label();
			$collection->Description = $co->description();
			$collection->Priority = $co->priority();
			$collection->Visibility = $co->visible();
			$collection->Color = $co->color();
			$list[] = $collection;
		}
		// return collection of collections
		return $list;
	}

	/**
	 * retrieve entity from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityFetch(string $location, string $id, string $granularity = 'D'): ?TaskObject {
		// construct request
		$r0 = new TaskGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target($id);
		// select properties to return
		if ($granularity === 'B') {
			$r0->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to Task object
		$eo = $this->toTaskObject($response->object(0));
		$eo->Signature = $this->generateSignature($eo);

		return $eo;
	}

	/**
	 * create entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityCreate(string $location, TaskObject $so): ?TaskObject {
		// convert entity
		$entity = $this->fromTaskObject($so);
		// construct set request
		$r0 = new TaskSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->create('1', $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return entity
		if (isset($response->created()['1']['id'])) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $response->created()['1']['id'];
			$ro->CreatedOn = isset($response->created()['1']['updated']) ? new DateTimeImmutable($response->created()['1']['updated']) : null;
			$ro->ModifiedOn = $ro->CreatedOn;
			$ro->Signature = $this->generateSignature($ro);
			return $ro;
		} else {
			return null;
		}
	}

	/**
	 * update entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityModify(string $location, string $id, TaskObject $so): ?TaskObject {
		// convert entity
		$entity = $this->fromTaskObject($so);
		// construct set request
		$r0 = new TaskSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($id, $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to Task object
		if (array_key_exists($id, $response->updated())) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $id;
			$ro->ModifiedOn = isset($response->updated()[$id]['updated']) ? new DateTimeImmutable($response->updated()[$id]['updated']) : null;
			$ro->Signature = $this->generateSignature($ro);
		} else {
			$ro = null;
		}
		// return entity information
		return $ro;
	}

	/**
	 * delete entity from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDelete(string $location, string $id): string {
		// construct set request
		$r0 = new TaskSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->delete($id);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string)$response->deleted()[0];
	}

	/**
	 * copy entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityCopy(string $sourceLocation, string $id, string $destinationLocation): string {
		return '';
	}

	/**
	 * move entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityMove(string $sourceLocation, string $id, string $destinationLocation): string {
		// construct set request
		$r0 = new TaskSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->in($destinationLocation);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string)$id : '';
	}

	/**
	 * retrieve entities from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string|null $location Id of parent collection
	 * @param string|null $granularity Amount of detail to return
	 * @param IRange|null $range Range of collections to return
	 * @param IFilter|null $filter Properties to filter by
	 * @param ISort|null $sort Properties to sort by
	 */
	public function entityList(?string $location = null, ?string $granularity = null, ?IRangeTally $range = null, ?IFilter $filter = null, ?ISort $sort = null, ?int $depth = null): array {
		// construct request
		$r0 = new TaskQuery($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// define location
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// define filter
		if ($filter !== null) {
			foreach ($filter->conditions() as $condition) {
				[$operator, $property, $value] = $condition;
				match($property) {
					'before' => $r0->filter()->before($value),
					'after' => $r0->filter()->after($value),
					'uid' => $r0->filter()->uid($value),
					default => null
				};
			}
		}
		// define sort
		if ($sort !== null) {
			foreach ($sort->conditions() as $condition) {
				[$property, $direction] = $condition;
				match($property) {
					'created' => $r0->sort()->created($direction),
					'modified' => $r0->sort()->updated($direction),
					'start' => $r0->sort()->start($direction),
					'uid' => $r0->sort()->uid($direction),
					default => null
				};
			}
		}
		// define order
		if ($sort !== null) {
			foreach ($sort->conditions() as $condition) {
				match($condition['attribute']) {
					'created' => $r0->sort()->created($condition['direction']),
					'modified' => $r0->sort()->updated($condition['direction']),
					'start' => $r0->sort()->start($condition['direction']),
					'uid' => $r0->sort()->uid($condition['direction']),
					'recurrence' => $r0->sort()->recurrence($condition['direction']),
					default => null
				};
			}
		}
		// construct request
		$r1 = new TaskGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// select properties to return
		if ($granularity === 'B') {
			$r1->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to message objects
		$state = $response->state();
		$list = $response->objects();
		foreach ($list as $id => $entry) {
			$list[$id] = $this->toTaskObject($entry);
		}
		// return message collection
		return ['list' => $list, 'state' => $state];

	}

	/**
	 * delta for entities in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @return DeltaObject
	 */
	public function entityDelta(?string $location, string $state, string $granularity = 'D'): DeltaObject {

		if (empty($state)) {
			$results = $this->entityList($location, 'B');
			$delta = new DeltaObject();
			$delta->signature = $results['state'];
			foreach ($results['list'] as $entry) {
				$delta->additions[] = $entry->ID;
			}
			return $delta;
		}
		if (empty($location)) {
			return $this->entityDeltaDefault($state, $granularity);
		} else {
			return $this->entityDeltaSpecific($location, $state, $granularity);
		}
	}

	/**
	 * delta of changes for specific collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDeltaSpecific(?string $location, string $state, string $granularity = 'D'): DeltaObject {
		// construct set request
		$r0 = new TaskQueryChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// set location constraint
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// set state constraint
		if (!empty($state)) {
			$r0->state($state);
		} else {
			$r0->state('0');
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command errored
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap object to delta object
		$delta = new DeltaObject();
		$delta->signature = $response->stateNew();
		$delta->additions = new BaseStringCollection($response->created());
		$delta->modifications = new BaseStringCollection($response->updated());
		$delta->deletions = new BaseStringCollection($response->deleted());

		return $delta;
	}

	/**
	 * delta of changes in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDeltaDefault(string $state, string $granularity = 'D'): DeltaObject {
		// construct set request
		$r0 = new TaskChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// set state constraint
		if (!empty($state)) {
			$r0->state($state);
		} else {
			$r0->state('');
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command errored
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap object to delta object
		$delta = new DeltaObject();
		$delta->signature = $response->stateNew();
		$delta->additions = new BaseStringCollection($response->created());
		$delta->modifications = new BaseStringCollection($response->updated());
		$delta->deletions = new BaseStringCollection($response->deleted());

		return $delta;
	}

	/**
	 * convert jmap object to Task object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function toTaskObject(TaskParametersResponse $so): TaskObject {
		// create object
		$eo = new TaskObject();
		// source origin
		$eo->Origin = OriginTypes::External;
		// id
		if ($so->id()) {
			$eo->ID = $so->id();
		}
		if ($so->in()) {
			$eo->CID = $so->in()[0];
		}
		// universal id
		if ($so->uid()) {
			$eo->UUID = $so->uid();
		}
		// creation date time
		if ($so->created()) {
			$eo->CreatedOn = $so->created();
		}
		// modification date time
		if ($so->updated()) {
			$eo->ModifiedOn = $so->updated();
		}

		return $eo;

	}

	/**
	 * convert Task object to jmap object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromTaskObject(TaskObject $eo): TaskParametersRequest {

		// create object
		$to = new TaskParametersRequest();
		// universal id
		if ($eo->UUID) {
			$to->uid($eo->UUID);
		}
		// creation date time
		if ($eo->CreatedOn) {
			$to->created($eo->CreatedOn);
		}
		// modification date time
		if ($eo->ModifiedOn) {
			$to->updated($eo->ModifiedOn);
		}

		return $to;

	}


	public function generateSignature(TaskObject $eo): string {

		// clone self
		$o = clone $eo;
		// remove non needed values
		unset(
			$o->Origin,
			$o->ID,
			$o->CID,
			$o->Signature,
			$o->CCID,
			$o->CEID,
			$o->CESN,
			$o->UUID,
			$o->CreatedOn,
			$o->ModifiedOn
		);

		// generate signature
		return md5(json_encode($o, JSON_PARTIAL_OUTPUT_ON_ERROR));

	}

}
