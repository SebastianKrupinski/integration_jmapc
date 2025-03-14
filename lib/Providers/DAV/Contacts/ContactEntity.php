<?php

namespace OCA\JMAPC\Providers\DAV\Contacts;

use OCA\JMAPC\Providers\DAV\Contacts\ContactCollection;
use OCA\JMAPC\Store\ContactEntity as ContactEntityData;
class ContactEntity implements \Sabre\CardDAV\ICard, \Sabre\DAVACL\IACL {

	private ContactCollection $_collection;
	private ContactEntityData $_entity;

	/**
	 * Entity Constructor
	 *
	 * @param Collection $calendar
	 * @param string $name
	 */
	public function __construct(ContactCollection $collection, ContactEntityData $entity) {
		$this->_collection = $collection;
		$this->_entity = $entity;
	}

	/**
	 * @inheritDoc
	 */
	function getOwner() {
		return $this->_collection->getOwner();
	}

	/**
	 * @inheritDoc
	 */
	function getGroup() {
		return $this->_collection->getGroup();
	}

	/**
	 * @inheritDoc
	 */
	function getACL() {
		return [
            [
                'privilege' => '{DAV:}all',
                'principal' => $this->getOwner(),
                'protected' => true,
            ],
        ];
	}

	/**
	 * @inheritDoc
	 */
	function setACL(array $acl) {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * @inheritDoc
	 */
	function getSupportedPrivilegeSet() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	function get() {
		return $this->_entity->getData();
	}

	/**
	 * @inheritDoc
	 */
	function put($data) {
		return $this->_collection->modifyFile($this->_entity, $data);
	}

	/**
	 * @inheritDoc
	 */
	function delete() {
		return $this->_collection->deleteFile($this->_entity);
	}

	/**
	 * @inheritDoc
	 */
	function getContentType() {
		return 'text/vcard; charset=utf-8';
	}

	/**
	 * @inheritDoc
	 */
	function getETag() {
		return $this->_entity->getSignature();
	}

	/**
	 * @inheritDoc
	 */
	function getSize() {
		return strlen($this->_entity->getData());
	}

	/**
	 * @inheritDoc
	 */
	function getName() {
		return $this->_entity->getUuid() . '.vcf';
	}

	/**
	 * @inheritDoc
	 */
	function setName($name) {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * @inheritDoc
	 */
	function getLastModified() {
		return time();
	}

}
