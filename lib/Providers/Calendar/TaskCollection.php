<?php

namespace OCA\JMAPC\Providers\Calendar;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Plugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\PropPatch;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\TaskStore;
use OCA\JMAPC\Store\CollectionEntity as CollectionEntityData;

class TaskCollection extends ExternalCalendar implements \Sabre\DAV\IMultiGet {

	private TaskStore $_store;
	private CollectionEntityData $_data;

	/**
	 * Collection constructor.
	 *
	 * @param TaskStore $store
	 * @param CollectionEntityData $data
	 */
	public function __construct(TaskStore &$store, CollectionEntityData $data) {
		
		parent::__construct(Application::APP_ID, $data->getUuid());

		$this->_store = $store;
		$this->_data = $data;

	}

	/**
     * retrieves the owner principal.
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
	function getOwner() {

		return 'principals/users/' . $this->_data->getUid();

	}

	/**
     * retrieves a group principal.
     *
     * This must be a url to a principal, or null if there's no group
     *
     * @return string|null
     */
	function getGroup() {

		return null;

	}

	/**
     * retrieves a list of ACE's for this collection.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to
     *      be updated.
     *
     * @return array
     */
	function getACL() {

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
     * alters the ACL for this collection
	 * 
	 * @param array $acl		list of ACE's
     */
	function setACL(array $acl) {

		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');

	}

	/**
     * retrieves a list of supported privileges for this node.
     *
     * The returned data structure is a list of nested privileges.
     * See Sabre\DAVACL\Plugin::getDefaultSupportedPrivilegeSet for a simple
     * standard structure.
     *
     * If null is returned from this method, the default privilege set is used,
     * which is fine for most common use cases.
     *
     * @return array|null
     */
	function getSupportedPrivilegeSet() {

		return null;

	}

	/**
	 * @inheritDoc
	 */
	function calendarQuery(array $filters) {

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
						$limit[] = ['dueon', '<=', $filter['time-range']['end']->format('U')];
					}
				}
			}
		}

		// retrieve entries
		$entries = $this->_store->entityFind($this->_data->getId(), $limit, ['uuid']);
		// list entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = $entry->getUuid();
		}
		// return list
		return $list;

	}

	/**
     * Create a new entity in this collection
     *
     * @param string          $id		Entity ID
     * @param resource|string $data		Entity Contents
     *
     * @return string|null				state on success / Null on fail
     */
	function createFile($id, $data = null) {

		// remove extension
		$id = str_replace('.ics', '', $id);
		// evaluate if data was sent as a resource
		if (is_resource($data)) {
            $data = stream_get_contents($data);
        }
		// evauate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// evaluate if task is related to another task
		if (strpos($data, 'RELATED-TO:') != false) {
			throw new \Sabre\DAV\Exception\Forbidden('Related tasks are not supported');
		}
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		$vo = $vo->VTODO;
		// data store entry
		$lo = [];
        $lo['data'] = $data;
		$lo['uuid'] = $id;
		$lo['uid'] = $this->_uid;
		$lo['sid'] = $this->_sid;
		$lo['cid'] = $this->_id;
		// calcualted properties
        $lo['size'] = strlen($data);
        $lo['signature'] = md5($data);
		// extracted properties from data
		$lo['label'] = (isset($vo->SUMMARY)) ? $this->extractString($vo->SUMMARY) : null;
        $lo['notes'] = (isset($vo->DESCRIPTION)) ? $this->extractString($vo->DESCRIPTION) : null;
		$lo['startson'] = (isset($vo->DTSTART)) ? $this->extractDateTime($vo->DTSTART)->setTimezone(new \DateTimeZone('UTC'))->format('U') : null;
		$lo['dueon'] = (isset($vo->DUE)) ? $this->extractDateTime($vo->DUE)->setTimezone(new \DateTimeZone('UTC'))->format('U') : null;
		// deposit entry to data store
		$this->_store->entityCreate($lo);
		// return state
		return $lo['signature'];

	}

	/**
     * modify a entity in this collection
     *
     * @param string          $id		Entity ID
     * @param resource|string $data		Entity Contents
     *
     * @return string|null				state on success / Null on fail
     */
	function modifyFile($id, $uuid, $data) {

		// evaluate if data was sent as a resource
		if (is_resource($data)) {
            $data = stream_get_contents($data);
        }
		// evauate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// evaluate if task is related to another task
		if (strpos($data, 'RELATED-TO:') != false) {
			throw new \Sabre\DAV\Exception\Forbidden('Related tasks not suppoerted');
		}
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		$vo = $vo->VTODO;
		// data store entry
		$lo = [];
        $lo['data'] = $data;
		$lo['uuid'] = $uuid;
		$lo['uid'] = $this->_uid;
		$lo['sid'] = $this->_sid;
		$lo['cid'] = $this->_id;
		// calcualted properties
        $lo['size'] = strlen($data);
        $lo['signature'] = md5($data);
		// extracted properties from data
		$lo['label'] = (isset($vo->SUMMARY)) ? $this->extractString($vo->SUMMARY) : null;
        $lo['notes'] = (isset($vo->DESCRIPTION)) ? $this->extractString($vo->DESCRIPTION) : null;
		$lo['startson'] = (isset($vo->DTSTART)) ? $this->extractDateTime($vo->DTSTART)->setTimezone(new \DateTimeZone('UTC'))->format('U') : null;
		$lo['dueon'] = (isset($vo->DUE)) ? $this->extractDateTime($vo->DUE)->setTimezone(new \DateTimeZone('UTC'))->format('U') : null;
		// deposit entry to data store
		$this->_store->entityModify($id, $lo);
		// return state
		return $lo['signature'];

	}

	/**
     * delete a entity in this collection
     *
     * @param string			$id		Entity ID
     *
     * @return bool				true on success / false on fail
     */
	function deleteFile($id) {

		// delete entry from data store and return result
		return $this->_store->entityDelete($id);

	}

	/**
     * retrieves all entities in this collection
     *
     * @return Entity[]
     */
	function getChildren() {
		
		// retrieve entries
		$entries = $this->_store->entityListByCollection($this->_data->getId());
		// list entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = new TaskEntity($this, $entry);
		}
		// return list
		return $list;

	}

	/**
     * retrieves a specific entity in this collection
     *
     * @param string $id		Entity ID
     *
     * @return Entity
     */
	function getChild($id) {

		// remove extension
		$id = str_replace('.ics', '', $id);
		// retrieve object properties
		$entry = $this->_store->entityFetchByUUID($this->_data->getId(), $id);
		// evaluate if object properties where retrieved 
		if ($entry->getUuid()) {
			return new TaskEntity($this, $entry);
		}
		else {
			return false;
		}

	}

	/**
	 * retrieves specific entities in this collection
     *
     * @param string[] $ids
     *
     * @return Entity[]
     */
    public function getMultipleChildren(array $ids) {

		// construct place holder
		$list = [];
		// retrieve entities
		foreach ($ids as $id) {
			// retrieve object properties
			$entry = $this->_store->entityFetchByUUID($this->_data->getId(), $id);
			// evaluate if object properties where retrieved 
			if ($entry->getUuid()) {
				$list[] = new TaskEntity($this, $entry);
			}
		}
		
		// return list
		return $list;

	}

	/**
     * Checks if a specific entity exists in this collection
     *
     * @param string $id
     *
     * @return bool
     */
	function childExists($id) {

		// remove extension
		$id = str_replace('.ics', '', $id);
		// confirm object exists
		return $this->_store->entityConfirmByUUID($this->_data->getId(), $id);

	}

	/**
     * Deletes this collection
     */
	function delete() {

		// delete local entities
		$this->_store->entityDeleteByCollection($this->_data->getId());
		// delete local collection
		$this->_store->collectionDelete($this->_data);

	}

	/**
     * Returns the last modification time, as a unix timestamp. Return null
     * if the information is not available.
     *
     * @return int|null
     */
	function getLastModified() {

		return time();

	}

	/**
     * alters properties of this collection
	 * 
	 * @param PropPatch $data
     */
	function propPatch(PropPatch $propPatch) {
		
		// retrieve mutations
		$mutations = $propPatch->getMutations();
		// evaluate if any mutations apply
		if (count($mutations) > 0) {
			// retrieve collection
			if ($this->_store->collectionConfirm($this->_data->getId())) {
				// evaluate if name was changed
				if (isset($mutations['{DAV:}displayname'])) {
					$this->_data->setLabel($mutations['{DAV:}displayname']);
				}
				// evaluate if color was changed
				if (isset($mutations['{http://apple.com/ns/ical/}calendar-color'])) {
					$this->_data->setColor($mutations['{http://apple.com/ns/ical/}calendar-color']);
				}
				// evaluate if enabled was changed
				if (isset($mutations['{http://owncloud.org/ns}calendar-enabled'])) {
					$this->_data->setVisible((int)$mutations['{http://owncloud.org/ns}calendar-enabled']);
				}
				// update collection
				if (count($this->_data->getUpdatedFields()) > 0) {
					$this->_store->collectionModify($this->_data);
				}
			}
		}

	}

	/**
     * retrieves a list of properties for this collection
     *
     * The properties list is a list of property names the client requested,
     * encoded in clark-notation {xmlnamespace}tagname
     *
     * @param array $properties
     *
     * @return array
     */
	function getProperties($properties) {
		
		// return collection properties
		return [
			'{DAV:}displayname' => $this->_data->getLabel(),
			'{http://apple.com/ns/ical/}calendar-color' => $this->_data->getColor(),
			'{http://owncloud.org/ns}calendar-enabled' => (string)$this->_data->getVisible(),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
		];
		
	}

	
	function extractString($property): string {
		return trim($property->getValue());
	}

	function extractDateTime($property): \DateTime|null {

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
