<?php

namespace OCA\JMAPC\Providers\DAV\Contacts;

use OCA\JMAPC\Store\Local\CollectionEntity as CollectionEntityData;

use OCA\JMAPC\Store\Local\ContactEntity as ContactEntityData;
use OCA\JMAPC\Store\Local\ContactStore;
use Sabre\CardDAV\IAddressBook;
use Sabre\DAV\IMultiGet;
use Sabre\DAV\IProperties;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Sync\ISyncCollection;

class ContactCollection implements IAddressBook, IProperties, IMultiGet, ISyncCollection {

	private ContactStore $_store;
	private CollectionEntityData $_collection;

	/**
	 * Collection constructor.
	 *
	 * @param ContactStore $store
	 * @param CollectionEntityData $data
	 */
	public function __construct(ContactStore &$store, CollectionEntityData $data) {
		
		// parent::__construct(Application::APP_ID, $data->getUuid());

		$this->_store = $store;
		$this->_collection = $data;

	}

	/**
	 * collection principal owner
	 *
	 * @return string|null
	 */
	public function getOwner(): ?string {

		return 'principals/users/' . $this->_collection->getUid();

	}

	/**
	 * collection principal group
	 *
	 * @return string|null
	 */
	public function getGroup(): ?string {

		return null;

	}

	/**
	 * collection id
	 */
	public function getName(): string {

		return $this->_collection->getUuid();

	}

	/**
	 * collection id
	 *
	 * @param string $id
	 */
	public function setName($id): void {
		
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');

	}

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
	public function getSupportedPrivilegeSet(): ?array {

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
	public function getSyncToken(): ?string {

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
	 * retrieves properties for this collection
	 *
	 * @param array $properties requested properties
	 *
	 * @return array
	 */
	public function getProperties($properties): array {
		
		// return collection properties
		return [
			'{DAV:}displayname' => $this->_collection->getLabel(),
			'{http://owncloud.org/ns}enabled' => (string)$this->_collection->getVisible(),
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
				if (isset($mutations['{http://owncloud.org/ns}enabled'])) {
					$this->_collection->setVisible((bool)$mutations['{http://owncloud.org/ns}enabled']);
					$propPatch->setResultCode('{http://owncloud.org/ns}enabled', 200);
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
	public function createDirectory($name): void {

		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');

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
	 * list all entities in this collection
	 *
	 * @return array<int,Entity>
	 */
	public function getChildren(): array {
		
		// retrieve entries
		$entries = $this->_store->entityListByCollection($this->_collection->getId());
		// list entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = new ContactEntity($this, $entry);
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
		$id = str_replace('.vcf', '', $id);
		// confirm object exists
		return $this->_store->entityConfirmByUUID($this->_collection->getId(), $id);

	}

	/**
	 * retrieve specific entities in this collection
	 *
	 * @param array<int,string> $ids
	 *
	 * @return array<int,Entity>
	 */
	public function getMultipleChildren(array $ids): array {

		// construct place holder
		$list = [];
		// retrieve entities
		foreach ($ids as $id) {
			// remove extension
			$id = str_replace('.vcf', '', $id);
			// retrieve object properties
			$entry = $this->_store->entityFetchByUUID($this->_collection->getId(), $id);
			// evaluate if object properties where retrieved
			if ($entry instanceof ContactEntityData) {
				$list[] = new ContactEntity($this, $entry);
			}
		}
		
		// return list
		return $list;

	}

	/**
	 * retrieve a specific entity in this collection
	 *
	 * @param string $id existing entity id
	 *
	 * @return Entity|false
	 */
	public function getChild($id): ContactEntity|false {

		// remove extension
		$id = str_replace('.vcf', '', $id);
		// retrieve object properties
		$entry = $this->_store->entityFetchByUUID($this->_collection->getId(), $id);
		// evaluate if object properties where retrieved
		if (isset($entry)) {
			return new ContactEntity($this, $entry);
		} else {
			throw new \Sabre\DAV\Exception\NotFound('Entity not found');
		}

	}

	/**
	 * create a entity in this collection
	 *
	 * @param string $id fresh entity id
	 * @param string $data fresh entity contents
	 *
	 * @return string fresh entity signature
	 */
	public function createFile($id, $data = null): string {

		// remove extension
		$id = str_replace('.vcf', '', $id);
		// evaluate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		// data store entry
		$entity = new ContactEntityData();
		// direct properties
		$entity->setData($data);
		$entity->setUid($this->_collection->getUid());
		$entity->setSid($this->_collection->getSid());
		$entity->setCid($this->_collection->getId());
		$entity->setUuid($id);
		// calculated properties
		$entity->setSignature(md5($data));
		// extracted properties
		$entity->setLabel(isset($vo->FN) ? trim($vo->FN->getValue()) : null);
		// deposit entity to data store
		$entity = $this->_store->entityCreate($entity);
		// return state
		return $entity->getSignature();

	}

	/**
	 * modify a entity in this collection
	 *
	 * @param contactEntityData $entity existing entity object
	 * @param string $data modified entity contents
	 *
	 * @return string modified entity signature
	 */
	public function modifyFile(ContactEntityData $entity, string $data): string {

		// evaluate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// read the data
		$vo = \Sabre\VObject\Reader::read($data);
		// direct properties
		$entity->setData($data);
		// calculated properties
		$entity->setSignature(md5($data));
		// extracted properties
		$entity->setLabel(isset($vo->FN) ? trim($vo->FN->getValue()) : null);
		// deposit entry to data store
		$entity = $this->_store->entityModify($entity);
		// return state
		return $entity->getSignature();

	}

	/**
	 * delete a entity in this collection
	 *
	 * @param ContactEntityData $entity existing entity object
	 *
	 * @return void
	 */
	public function deleteFile(ContactEntityData $entity): void {

		// delete entry from data store and return result
		$this->_store->entityDelete($entity);

	}

}
