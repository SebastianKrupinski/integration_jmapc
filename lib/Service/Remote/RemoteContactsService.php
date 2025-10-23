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
use Exception;

use JmapClient\Client;
use JmapClient\Requests\Contacts\AddressBookGet;
use JmapClient\Requests\Contacts\AddressBookParameters as AddressBookParametersRequest;
use JmapClient\Requests\Contacts\AddressBookSet;
use JmapClient\Requests\Contacts\ContactChanges;
use JmapClient\Requests\Contacts\ContactGet;
use JmapClient\Requests\Contacts\ContactParameters as ContactParametersRequest;
use JmapClient\Requests\Contacts\ContactQuery;
use JmapClient\Requests\Contacts\ContactQueryChanges;
use JmapClient\Requests\Contacts\ContactSet;
use JmapClient\Responses\Contacts\AddressBookParameters as AddressBookParametersResponse;
use JmapClient\Responses\Contacts\ContactParameters as ContactParametersResponse;
use JmapClient\Responses\ResponseException;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\BaseStringCollection;
use OCA\JMAPC\Objects\Contact\ContactCollectionObject;
use OCA\JMAPC\Objects\Contact\ContactObject as ContactObject;
use OCA\JMAPC\Objects\DeltaObject;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Store\Common\Filters\IFilter;
use OCA\JMAPC\Store\Common\Range\IRangeTally;
use OCA\JMAPC\Store\Common\Range\RangeAnchorType;
use OCA\JMAPC\Store\Common\Sort\ISort;

class RemoteContactsService {
	protected Client $dataStore;
	protected string $dataAccount;

	protected ?string $resourceNamespace = null;
	protected ?string $resourceCollectionLabel = null;
	protected ?string $resourceEntityLabel = null;

	protected array $collectionPropertiesDefault = [];
	protected array $collectionPropertiesBasic = [];
	protected array $entityPropertiesDefault = [];
	protected array $entityPropertiesBasic = [
		'id', 'addressbookId', 'uid'
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
				$this->dataAccount = $dataStore->sessionAccountDefault('contacts');
			}
		} else {
			$this->dataAccount = $dataAccount;
		}

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
	 * @return array<string,ContactCollectionObject>
	 */
	public function collectionList(?string $location = null, ?string $granularity = null, ?int $depth = null): array {
		// construct request
		$r0 = new AddressBookGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
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
		foreach ($response->objects() as $so) {
			if (!$so instanceof AddressBookParametersResponse) {
				continue;
			}
			$to = $this->toContactCollection($so);
			$to->Signature = $response->state();
			$list[] = $to;
		}
		// return collection of collections
		return $list;
	}

	/**
	 * retrieve properties for specific collection
	 *
	 * @since Release 1.0.0
	 */
	public function collectionFetch(string $id): ?ContactCollectionObject {
		// construct request
		$r0 = new AddressBookGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		if (!empty($id)) {
			$r0->target($id);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to collection object
		$so = $response->object(0);
		$to = null;
		if ($so instanceof AddressBookParametersResponse) {
			$to = $this->toContactCollection($so);
			$to->Signature = $response->state();
		}
		return $to;
	}

	/**
	 * create collection in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function collectionCreate(ContactCollectionObject $so): string {
		// convert entity
		$to = $this->fromContactCollection($so);
		// construct request
		$r0 = new AddressBookSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->create('1', $to);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return (string)$response->created()['1']['id'];
	}

	/**
	 * modify collection in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function collectionModify(string $id, ContactCollectionObject $so): string {
		// convert entity
		$to = $this->fromContactCollection($so);
		// construct request
		$r0 = new AddressBookSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->update($id, $to);
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
		$r0 = new AddressBookSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->delete($id);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return (string)$response->deleted()[0];
	}

	/**
	 * retrieve entity from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $location Id of collection
	 * @param string $identifier Id of entity
	 * @param string $granularity Amount of detail to return
	 *
	 * @return EventObject|null
	 */
	public function entityFetch(string $location, string $id, string $granularity = 'D'): ?ContactObject {
		// construct request
		$r0 = new ContactGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target($id);
		// select properties to return
		if ($granularity === 'B') {
			$r0->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to event object
		$so = $response->object(0);
		if ($so instanceof ContactParametersResponse) {
			$to = $this->toContactObject($so);
			$to->Signature = $this->generateSignature($to);
		}

		return $to ?? null;
	}

	/**
	 * retrieve entity(ies) from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $location Id of collection
	 * @param array<string> $identifiers Id of entity
	 * @param string $granularity Amount of detail to return
	 *
	 * @return array<string,ContactObject>
	 */
	public function entityFetchMultiple(string $location, array $identifiers, string $granularity = 'D'): array {
		// construct request
		$r0 = new ContactGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target(...$identifiers);
		// select properties to return
		if ($granularity === 'B') {
			$r0->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object(s) to event object
		$list = $response->objects();
		foreach ($list as $id => $so) {
			if (!$so instanceof ContactParametersResponse) {
				continue;
			}
			$to = $this->toContactObject($so);
			$to->Signature = $this->generateSignature($to);
			$list[$id] = $so;
		}
		// return object(s)
		return $list;
	}

	/**
	 * create entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityCreate(string $location, ContactObject $so): ?ContactObject {
		// convert entity
		$entity = $this->fromContactObject($so);
		// construct set request
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
	public function entityModify(string $location, string $id, ContactObject $so): ?ContactObject {
		// convert entity
		$entity = $this->fromContactObject($so);
		// construct set request
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($id, $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to event object
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
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->delete($id);
		// transmit request and receive response
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
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->in($destinationLocation);
		// transmit request and receive response
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
		$r0 = new ContactQuery($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// define location
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// define filter
		if ($filter !== null) {
			foreach ($filter->conditions() as $condition) {
				[$operator, $property, $value] = $condition;
				match($property) {
					'createBefore' => $r0->filter()->createdBefore($value),
					'createAfter' => $r0->filter()->createdAfter($value),
					'modifiedBefore' => $r0->filter()->updatedBefore($value),
					'modifiedAfter' => $r0->filter()->updatedAfter($value),
					'uid' => $r0->filter()->uid($value),
					'kind' => $r0->filter()->kind($value),
					'member' => $r0->filter()->member($value),
					'text' => $r0->filter()->text($value),
					'name' => $r0->filter()->name($value),
					'nameGiven' => $r0->filter()->nameGiven($value),
					'nameSurname' => $r0->filter()->nameSurname($value),
					'nameAlias' => $r0->filter()->nameAlias($value),
					'organization' => $r0->filter()->organization($value),
					'email' => $r0->filter()->mail($value),
					'phone' => $r0->filter()->phone($value),
					'address' => $r0->filter()->address($value),
					'note' => $r0->filter()->note($value),
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
					'nameGiven' => $r0->sort()->nameGiven($direction),
					'nameSurname' => $r0->sort()->nameSurname($direction),
					default => null
				};
			}
		}
		// define range
		if ($range !== null) {
			if ($range->anchor() === RangeAnchorType::ABSOLUTE) {
				$r0->limitAbsolute($range->getPosition(), $range->getCount());
			}
			if ($range->anchor() === RangeAnchorType::RELATIVE) {
				$r0->limitRelative($range->getPosition(), $range->getCount());
			}
		}
		// construct get request
		$r1 = new ContactGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
		// convert json objects to contact objects
		$state = $response->state();
		$list = $response->objects();
		foreach ($list as $id => $entry) {
			$list[$id] = $this->toContactObject($entry);
		}
		// return status object
		return ['list' => $list, 'state' => $state];
	}

	/**
	 * delta for entities in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @return DeltaObject
	 */
	public function entityDelta(?string $location, string $state): DeltaObject {

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
			return $this->entityDeltaDefault($state);
		} else {
			return $this->entityDeltaSpecific($location, $state);
		}
	}

	/**
	 * delta of changes for specific collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDeltaSpecific(?string $location, string $state): DeltaObject {
		// construct set request
		$r0 = new ContactQueryChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
		$r0 = new ContactChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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

	private function toContactCollection(AddressBookParametersResponse $so): ContactCollectionObject {
		$to = new ContactCollectionObject();
		$to->Id = $so->id();
		$to->Label = $so->label();
		$to->Description = $so->description();
		$to->Priority = $so->priority();
		return $to;
	}

	public function fromContactCollection(ContactCollectionObject $so): AddressBookParametersRequest {
		// create object
		$to = new AddressBookParametersRequest();

		if ($so->Label !== null) {
			$to->label($so->Label);
		}
		if ($so->Description !== null) {
			$to->description($so->Description);
		}
		if ($so->Priority !== null) {
			$to->priority($so->Priority);
		}

		return $to;
	}

	/**
	 * convert jmap object to contact object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function toContactObject($so): ContactObject {

		// create object
		$do = new ContactObject();
		// source origin
		$do->Origin = OriginTypes::External;
		// id
		if ($so->id()) {
			$do->ID = $so->id();
		}
		// universal id
		if ($so->uid()) {
			$do->UUID = $so->uid();
		}

		return $do;

	}

	/**
	 * convert contact object to jmap object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromContactObject(ContactObject $so): mixed {

		// create object
		$to = new ContactParametersRequest();

		return $to;

	}

	public function generateSignature(ContactObject $eo): string {

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
