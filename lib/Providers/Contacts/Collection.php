<?php

namespace OCA\JMAPC\Providers\Contacts;

use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Plugin;
use Sabre\DAV\PropPatch;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\ContactStore;

class Collection extends ExternalAddressBook implements \Sabre\DAV\IMultiGet {

	private ContactStore $_store;
	private int $_id;
	private string $_uuid;
	private string $_uid;
	private string $_label;
	private string $_color;

	/**
	 * Collection constructor.
	 *
	 * @param string $id
	 * @param string $uid
	 * @param string $uuid
	 * @param string $label
	 * @param string $color
	 */
	public function __construct(ContactStore &$store, string $id, string $uid, string $uuid, string $label, string $color) {
		
		parent::__construct(Application::APP_ID, $uuid);

		$this->_store = $store;
		$this->_id = $id;
		$this->_uid = $uid;
		$this->_uuid = $uuid;
		$this->_label = $label;
		$this->_color = $color;

	}

	/**
     * retrieves the owner principal.
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
	function getOwner() {

		return 'principals/users/' . $this->_uid;

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
     * which is fine for most common usecases.
     *
     * @return array|null
     */
	function getSupportedPrivilegeSet() {

		return null;

	}

	/**
     * Create a new entity in this collection
     *
     * @param string          $uuid		Entity UUID
     * @param resource|string $data		Entity Contents
     *
     * @return string|null				state on success / Null on fail
     */
	function createFile($uuid, $data = null) {

		// remove extension
		$uuid = str_replace('.vcf', '', $uuid);
		// evaluate if data was sent as a resource
		if (is_resource($data)) {
            $data = stream_get_contents($data);
        }
		// evauate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		// data store entry
		$lo = [];
		$lo['uuid'] = $uuid;
		$lo['uid'] = $this->_uid;
		$lo['cid'] = $this->_id;
        $lo['label'] = trim($vo->FN->getValue());
        $lo['size'] = strlen($data);
        $lo['signature'] = md5($data);
		$lo['data'] = $data;
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
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		// data store entry
		$lo = [];
		$lo['uuid'] = $uuid;
		$lo['uid'] = $this->_uid;
		$lo['cid'] = $this->_id;
        $lo['label'] = trim($vo->FN->getValue());
        $lo['size'] = strlen($data);
        $lo['signature'] = md5($data);
		$lo['data'] = $data;
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
		$entries = $this->_store->entityListByCollection($this->_uid, $this->_id);
		// list entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = new Entity($this, $entry['id'], $entry['uuid'], (string) $entry['label'], $entry);
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
		$id = str_replace('.vcf', '', $id);
		// retrieve object properties
		$entry = $this->_store->entityFetchByUUID($this->_uid, $id);
		// evaluate if object properties where retrieved 
		if (isset($entry['uuid'])) {
			return new Entity($this, $entry['id'], $entry['uuid'], (string) $entry['label'], $entry);
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
			$entry = $this->_store->entityFetchByUUID($this->_uid, $id);
			// evaluate if object properties where retrieved 
			if (isset($entry['uuid'])) {
				$list[] = new Entity($this, $entry['id'], $entry['uuid'], (string) $entry['label'], $entry);
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
		$id = str_replace('.vcf', '', $id);
		// confim object exists
		return $this->_store->entityConfirmByUUID($this->_uid, $id);

	}

	/**
     * Deletes this collection
     */
	function delete() {

		// delete local entities
		$this->_store->entityDeleteByCollection($this->_uid, $this->_id);
		// delete local collection
		$this->_store->collectionDelete($this->_id);

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
	function propPatch(PropPatch $properties) {
		
		// retrieve mutations
		$mutations = $properties->getMutations();
		// evaluate if any mutations apply
		if (isset($mutations['{DAV:}displayname']) || isset($mutations['{http://apple.com/ns/ical/}calendar-color'])) {
			// retrieve collection
			if ($this->_store->collectionConfirm($this->_id)) {
				// construct place holder
				$entry = [];
				// evaluate if name was changed
				if (isset($mutations['{DAV:}displayname'])) {
					// assign new name
					$entry['label'] = ($mutations['{DAV:}displayname']);
				}
				// evaluate if color was changed
				if (isset($mutations['{http://apple.com/ns/ical/}calendar-color'])) {
					// assign new color
					$entry['color'] = ($mutations['{http://apple.com/ns/ical/}calendar-color']);
				}
				// update collection
				if (count($entry) > 0) {
					$this->_store->collectionModify($this->_id, $entry);
				}
			}
		}

	}

	/**
     * retrieves a list of properties for this collection
     *
     * The properties list is a list of propertynames the client requested,
     * encoded in clark-notation {xmlnamespace}tagname
     *
     * @param array $properties
     *
     * @return array
     */
	function getProperties($properties) {
		
		// return collection properties
		return [
			'{DAV:}displayname' => $this->_label,
		];

	}

}
