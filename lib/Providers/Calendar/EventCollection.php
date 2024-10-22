<?php

namespace OCA\JMAPC\Providers\Calendar;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Plugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\PropPatch;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\EventStore;
use OCA\JMAPC\Store\CollectionEntity as CollectionEntityData;
use OCA\JMAPC\Store\EventEntity as EventEntityData;

class EventCollection extends ExternalCalendar implements \Sabre\DAV\IMultiGet {

	private EventStore $_store;
	private CollectionEntityData $_collection;

	/**
	 * Collection constructor.
	 *
	 * @param EventStore $store
	 * @param CollectionEntityData $data
	 */
	public function __construct(EventStore &$store, CollectionEntityData $data) {
		
		parent::__construct(Application::APP_ID, $data->getUuid());

		$this->_store = $store;
		$this->_collection = $data;

	}

	/**
     * collection principal owner
     *
     * @return string|null
     */
	public function getOwner(): string|null {

		return 'principals/users/' . $this->_collection->getUid();

	}

	/**
     * collection principal group
     *
     * @return string|null
     */
	public function getGroup(): string|null {

		return null;

	}

	/**
     * collection permissions
	 * 
     * @return array
     */
	public function getACL(): array {

		return [
			
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			/*
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-read',
				'protected' => true,
			],
			*/
			[
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			/*
			[
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}write-properties',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}write-properties',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			]
			*/
		];

	}

	/**
	 * collection permissions
	 * 
	 * @return void
     */
	public function setACL(array $acl): void {

		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');

	}

	/**
	 * supported permissions
	 * 
     * @return array|null
     */
	public function getSupportedPrivilegeSet(): array|null {

		return null;

	}

	/**
     * collection modification timestamp
     *
     * @return int|null
     */
	public function getLastModified() {

		return null;

	}

	/**
     * determines if this collection is shared
	 * 
	 * @return bool
     */
	public function isShared(): bool {

		return false;

	}

	/**
     * retrieves properties for this collection
     *
     * @param array $properties			requested properties
     *
     * @return array
     */
	public function getProperties($properties): array {
		
		// return collection properties
		return [
			'{DAV:}displayname' => $this->_collection->getLabel(),
			'{http://apple.com/ns/ical/}calendar-color' => $this->_collection->getColor(),
			'{http://owncloud.org/ns}calendar-enabled' => (string)$this->_collection->getVisible(),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
		];
		
	}

	/**
     * modifies properties of this collection
	 * 
	 * @param PropPatch $data
	 * 
	 * @return void
     */
	public function propPatch(PropPatch $propPatch): void {
		
		// retrieve mutations
		$mutations = $propPatch->getMutations();
		// evaluate if any mutations apply
		if (count($mutations) > 0) {
			// retrieve collection
			if ($this->_store->collectionConfirm($this->_collection->getId())) {
				// evaluate if name was changed
				if (isset($mutations['{DAV:}displayname'])) {
					$this->_collection->setLabel($mutations['{DAV:}displayname']);
				}
				// evaluate if color was changed
				if (isset($mutations['{http://apple.com/ns/ical/}calendar-color'])) {
					$this->_collection->setColor($mutations['{http://apple.com/ns/ical/}calendar-color']);
				}
				// evaluate if enabled was changed
				if (isset($mutations['{http://owncloud.org/ns}calendar-enabled'])) {
					$this->_collection->setVisible((int)$mutations['{http://owncloud.org/ns}calendar-enabled']);
				}
				// update collection
				if (count($this->_collection->getUpdatedFields()) > 0) {
					$this->_store->collectionModify($this->_collection);
				}
			}
		}

	}

	/**
     * Deletes this collection and all entities
	 * 
	 * @return void
     */
	public function delete(): void {

		// delete local entities
		$this->_store->entityDeleteByCollection($this->_collection->getId());
		// delete local collection
		$this->_store->collectionDelete($this->_collection);

	}

	/**
	 * find entities in this collection
	 * 
	 * @return array<int,string>
	 */
	public function calendarQuery(array $filters): array {

		// construct place holder
		$limit = [];
		//
		if (is_array($filters) && is_array($filters['comp-filters'])) {
			foreach ($filters['comp-filters'] as $filter) {
				if (is_array($filter['time-range'])) {
					if (isset($filter['time-range']['start'])) {
						$limit[] = ['startson', '>=', $filter['time-range']['start']->format('U')];
					}
					if (isset($filter['time-range']['end'])) {
						$limit[] = ['endson', '<=', $filter['time-range']['end']->format('U')];
					}
				}
			}
		}

		// retrieve entries
		$entries = $this->_store->entityFind($this->_collection->getId(), $limit, ['uuid']);
		// list entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = $entry->getUuid();
		}
		// return list
		return $list;

	}

	/**
     * list all entities in this collection
     *
     * @return array<int,EventEntity>
     */
	public function getChildren(): array {
		
		// retrieve entries
		$entries = $this->_store->entityListByCollection($this->_collection->getId());
		// list entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = new EventEntity($this, $entry);
		}
		// return list
		return $list;

	}

	/**
     * determine if a specific entity exists in this collection
     *
     * @param string $id
     *
     * @return bool
     */
	public function childExists($id): bool {

		// remove extension
		$id = str_replace('.ics', '', $id);
		// confirm object exists
		return $this->_store->entityConfirmByUUID($this->_collection->getId(), $id);

	}

	/**
	 * retrieve specific entities in this collection
     *
     * @param array<int,string> $ids
     *
     * @return array<int,EventEntity>
	 */
    public function getMultipleChildren(array $ids): array {

		// construct place holder
		$list = [];
		// retrieve entities
		foreach ($ids as $id) {
			// retrieve object properties
			$entry = $this->_store->entityFetchByUUID($this->_collection->getId(), $id);
			// evaluate if object properties where retrieved 
			if ($entry->getUuid()) {
				$list[] = new EventEntity($this, $entry);
			}
		}
		
		// return list
		return $list;

	}

	/**
     * retrieve a specific entity in this collection
     *
     * @param string $id				existing entity id
     *
     * @return EventEntity|false
     */
	public function getChild($id): EventEntity|false {

		// remove extension
		$id = str_replace('.ics', '', $id);
		// retrieve object properties
		$entry = $this->_store->entityFetchByUUID($this->_collection->getId(), $id);
		// evaluate if object properties where retrieved 
		if (isset($entry)) {
			return new EventEntity($this, $entry);
		}
		else {
			return false;
		}

	}

	/**
     * create a entity in this collection
     *
     * @param string $id				fresh entity id
     * @param string $data				fresh entity contents
     *
     * @return string					fresh entity signature
     */
	public function createFile($id, $data = null): string {

		// remove extension
		$id = str_replace('.ics', '', $id);
		// evaluate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		$vo = $vo->VEVENT;
		// data store entry
		$entity = new EventEntityData();
		// direct properties
        $entity->setData($data);
		$entity->setUid($this->_collection->getUid());
		$entity->setSid($this->_collection->getSid());
		$entity->setCid($this->_collection->getId());
		$entity->setUuid($id);
		// calculated properties
		$entity->setSignature(md5($data));
		// extracted properties
		$entity->setLabel(isset($vo->SUMMARY) ? $this->extractString($vo->SUMMARY) : null);
        $entity->setDescription(isset($vo->DESCRIPTION) ? $this->extractString($vo->DESCRIPTION) : null);
		$entity->setStartson($this->extractDateTime($vo->DTSTART)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
		$entity->setEndson($this->extractDateTime($vo->DTEND)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
		// deposit entity to data store
		$entity = $this->_store->entityCreate($entity);
		// return state
		return $entity->getSignature();

	}

	/**
     * modify a entity in this collection
     *
     * @param EventEntityData $entity	existing entity object
     * @param string $data				modified entity contents
     *
     * @return string					modified entity signature
     */
	public function modifyFile(EventEntityData $entity, string $data): string {
		
		// evaluate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		$vo = $vo->VEVENT;
		// direct properties
        $entity->setData($data);
		// calculated properties
		$entity->setSignature(md5($data));
		// extracted properties
		$entity->setLabel(isset($vo->SUMMARY) ? $this->extractString($vo->SUMMARY) : null);
        $entity->setDescription(isset($vo->DESCRIPTION) ? $this->extractString($vo->DESCRIPTION) : null);
		$entity->setStartson($this->extractDateTime($vo->DTSTART)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
		$entity->setEndson($this->extractDateTime($vo->DTEND)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
		// deposit entry to data store
		$entity = $this->_store->entityModify($entity);
		// return state
		return $entity->getSignature();

	}

	/**
     * delete a entity in this collection
     *
     * @param EventEntityData $entity	existing entity object
     *
     * @return void
     */
	public function deleteFile(EventEntityData $entity): void {

		// delete entry from data store and return result
		$this->_store->entityDelete($entity);

	}

	/**
	 * converts entity text property to string
	 * 
	 * @return string
	 */
	protected function extractString($property): string {
		return trim($property->getValue());
	}

	/**
	 * converts entity date property to DateTime
	 * 
	 * @return DateTime|null
	 */
	protected function extractDateTime($property): \DateTime|null {

		if (isset($property)) {
			if (isset($property->parameters['TZID'])) {
				$tz = new \DateTimeZone($property->parameters['TZID']->getValue());
			}
			else {
				$tz = new \DateTimeZone('UTC');
			}
			return new \DateTime($property->getValue(), $tz);
		}
		else {
			return null;
		}

	}

}
