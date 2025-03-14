<?php

namespace OCA\JMAPC\Providers\DAV\Calendar;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use OCA\DAV\CalDAV\EventReaderRRule;
use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Plugin;
use OCA\JMAPC\AppInfo\Application;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\PropPatch;

use OCA\JMAPC\Store\EventStore;
use OCA\JMAPC\Store\CollectionEntity as CollectionEntityData;
use OCA\JMAPC\Store\EventEntity as EventEntityData;
use Sabre\CalDAV\ICalendar;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\IMultiGet;
use Sabre\DAV\IProperties;
use Sabre\DAV\Sync\ISyncCollection;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Recur\NoInstancesException;

class EventCollection extends ExternalCalendar implements ICalendar, IProperties, IMultiGet, ISyncCollection {
	
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
     * collection id
     */
	/*
    public function getName(): string {

		return 'app-generated--' . Application::APP_ID . '--'. $this->_collection->getUuid();

	}
	*/

    /**
     * collection id
     *
     * @param string $id
     */
	/*
    public function setName($id): void {
		
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');

	}
	*/

	/**
     * collection permissions
	 * 
     * @return array
     */
	public function getACL(): array {

		return [
			[
				'privilege' => '{DAV:}all',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
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
     * collection mutation signature
	 * 
     * @return string|null
     */
    public function getSyncToken(): string|null {

		return $this->_store->chronicleApex($this->_collection->getId(), true);

	}

    /**
     * collection delta 
	 * 
     * @param string $token
     * @param int $level
     * @param int $limit
     *
     * @return array|null
     */
    public function getChanges($token, $level, $limit = null): array {

		$delta = $this->_store->chronicleReminisce($this->_collection->getId(), (string)$token, $limit);

		$changes['added'] = array_column($delta['additions'], 'uuid');
		$changes['modified'] = array_column($delta['modifications'], 'uuid');
		$changes['deleted'] = array_column($delta['deletions'], 'uuid');
		$changes['syncToken'] = $delta['stamp'];

		return $changes;

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
					$propPatch->setResultCode('{DAV:}displayname', 200);
				}
				// evaluate if color was changed
				if (isset($mutations['{http://apple.com/ns/ical/}calendar-color'])) {
					$this->_collection->setColor($mutations['{http://apple.com/ns/ical/}calendar-color']);
					$propPatch->setResultCode('{http://apple.com/ns/ical/}calendar-color', 200);
				}
				// evaluate if enabled was changed
				if (isset($mutations['{http://owncloud.org/ns}calendar-enabled'])) {
					$this->_collection->setVisible((int)$mutations['{http://owncloud.org/ns}calendar-enabled']);
					$propPatch->setResultCode('{http://owncloud.org/ns}calendar-enabled', 200);
				}
				// update collection
				if (count($this->_collection->getUpdatedFields()) > 0) {
					$this->_store->collectionModify($this->_collection);
				}
			}
		}

	}

	/**
     * creates sub collection
     *
     * @param string $name
     */
	/*
    public function createDirectory($name): void {

		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');

	}
	*/

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
						$limit[] = ['startson', '<=', $filter['time-range']['end']->format('U')];
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
			if ($entry instanceof EventEntityData) {
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
			throw new \Sabre\DAV\Exception\NotFound('Entity not found');
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
		$vObject = \Sabre\VObject\Reader::read($data);
		// normalize properties
		$this->normalizeProperties($vObject);
		$vBase = $vObject->getBaseComponent();
		// data store entry
		$entity = new EventEntityData();
		// direct properties
        $entity->setData($vObject->serialize());
		$entity->setUid($this->_collection->getUid());
		$entity->setSid($this->_collection->getSid());
		$entity->setCid($this->_collection->getId());
		$entity->setUuid($id);
		// calculated properties
		$entity->setSignature(md5($data));
		// extracted properties
		$entity->setLabel(isset($vBase->SUMMARY) ? $this->extractString($vBase->SUMMARY) : null);
        $entity->setDescription(isset($vBase->DESCRIPTION) ? $this->extractString($vBase->DESCRIPTION) : null);
		$entity->setStartson($this->extractDateTime($vBase->DTSTART)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
		$entity->setEndson($this->extractDateTime($vBase->DTEND)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
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
		$vObject = \Sabre\VObject\Reader::read($data);
		// normalize properties
		$this->normalizeProperties($vObject);
		$vBase = $vObject->getBaseComponent();
		// direct properties
        $entity->setData($vObject->serialize());
		// calculated properties
		$entity->setSignature(md5($data));
		// extracted properties
		$entity->setLabel(isset($vBase->SUMMARY) ? $this->extractString($vBase->SUMMARY) : null);
        $entity->setDescription(isset($vBase->DESCRIPTION) ? $this->extractString($vBase->DESCRIPTION) : null);
		$entity->setStartson($this->extractDateTime($vBase->DTSTART)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
		$entity->setEndson($this->extractEndDate($vBase)->setTimezone(new \DateTimeZone('UTC'))->format('U'));
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

	protected function extractEndDate(Component $component): DateTimeImmutable {

		$startDate = $component->DTSTART->getDateTime();
		$endDate = clone $startDate;
		// Recurring
		if ($component->RRULE || $component->RDATE) {
			// RDATE can have both instances and multiple values
			// RDATE;TZID=America/Toronto:20250701T000000,20260701T000000
			// RDATE;TZID=America/Toronto:20270701T000000
			if ($component->RDATE) {
				foreach ($component->RDATE as $instance) {
					foreach ($instance->getDateTimes() as $entry) {
						if ($entry > $endDate) {
							$endDate = $entry;
						}
					}
				}
			}
			// RRULE can be infinate or limited by a UNTIL or COUNT
			if ($component->RRULE) {
				try {
					$rule = new EventReaderRRule($component->RRULE->getValue(), $startDate);
					$endDate = $rule->isInfinite() ? new DateTime('2038-01-01') : $rule->concludes();
				} catch (NoInstancesException $e) {
					$this->logger->debug('Caught no instance exception for calendar data. This usually indicates invalid calendar data.', [
						'app' => 'dav',
						'exception' => $e,
					]);
					throw new Forbidden($e->getMessage());
				}
			}
			// Singleton
		} else {
			if ($component->DTEND instanceof \Sabre\VObject\Property\ICalendar\DateTime) {
				// VEVENT component types
				$endDate = $component->DTEND->getDateTime();
			} elseif ($component->DURATION  instanceof \Sabre\VObject\Property\ICalendar\Duration) {
				// VEVENT / VTODO component types
				$endDate = $startDate->add($component->DURATION->getDateInterval());
			} elseif ($component->DUE  instanceof \Sabre\VObject\Property\ICalendar\DateTime) {
				// VTODO component types
				$endDate = $component->DUE->getDateTime();
			} elseif ($component->name === 'VEVENT' && !$component->DTSTART->hasTime()) {
				// VEVENT component type without time is automatically one day
				$endDate = (clone $startDate)->modify('+1 day');
			}
		}

		return DateTimeImmutable::createFromInterface($endDate);
	}

	protected function normalizeProperties(VCalendar $vObject): void {

		foreach ($vObject->getComponents() as $component) {
			if ($component->name === 'VTIMEZONE') {
				continue;
			}
			// normalize ORGANIZER
			if ($component->ORGANIZER !== null && $component->ORGANIZER->{'X-ID'}?->getValue() === null) {
				$component->ORGANIZER->add('X-ID', uniqid());
			}
			// normalize ATTENDEE(s)
			foreach ($component->ATTENDEE as $entry) {
				if (empty($entry->parameters()['X-ID']?->getValue())) {
					$entry->add('X-ID', uniqid());
				}
			}
			// normalize LOCATION(s)
			foreach ($component->LOCATION as $entry) {
				if (empty($entry->parameters()['X-ID']?->getValue())) {
					$entry->add('X-ID', uniqid());
				}
			}
			// normalize VALARM(s)
			foreach ($component->VALARM as $entry) {
				if ($entry->{'X-ID'} === null) {
					$entry->add('X-ID', uniqid());
				}
				elseif (empty($entry->{'X-ID'}?->getValue())) {
					$entry->{'X-ID'}->setValue(uniqid());
				}
			}
			// normalize ATTACH(s)
			foreach ($component->ATTACH as $entry) {
				if (empty($entry->parameters()['X-ID']?->getValue())) {
					$entry->add('X-ID', uniqid());
				}
			}
		}

	}

}
