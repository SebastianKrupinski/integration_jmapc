<?php

namespace OCA\JMAPC\Providers\DAV\Contacts;

use OCA\JMAPC\Store\Local\ContactEntity as ContactEntityData;

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
	public function getOwner() {
		return $this->_collection->getOwner();
	}

	/**
	 * @inheritDoc
	 */
	public function getGroup() {
		return $this->_collection->getGroup();
	}

	/**
	 * @inheritDoc
	 */
	public function getACL() {
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
	public function setACL(array $acl) {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedPrivilegeSet() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function get() {
		return $this->_entity->getData();
	}

	/**
	 * @inheritDoc
	 */
	public function put($data) {
		return $this->_collection->modifyFile($this->_entity, $data);
	}

	/**
	 * @inheritDoc
	 */
	public function delete() {
		return $this->_collection->deleteFile($this->_entity);
	}

	/**
	 * @inheritDoc
	 */
	public function getContentType() {
		return 'text/vcard; charset=utf-8';
	}

	/**
	 * @inheritDoc
	 */
	public function getETag() {
		return $this->_entity->getSignature();
	}

	/**
	 * @inheritDoc
	 */
	public function getSize() {
		return strlen($this->_entity->getData());
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->_entity->getUuid() . '.vcf';
	}

	/**
	 * @inheritDoc
	 */
	public function setName($name) {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * @inheritDoc
	 */
	public function getLastModified() {
		return time();
	}

}
