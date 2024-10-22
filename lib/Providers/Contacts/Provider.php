<?php

namespace OCA\JMAPC\Providers\Contacts;

use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\ContactStore;

class Provider implements IAddressBookProvider {

	private ContactStore $_store;

	public function __construct(ContactStore $Store) {
		$this->_store = $Store;
	}

	/**
	 * @inheritDoc
	 */
	public function getAppId(): string {
		return Application::APP_ID;
	}

	/**
	 * @inheritDoc
	 */
	public function fetchAllForAddressBookHome(string $principalUri): array {

		// retrieve collection(s)
		$collections = $this->_store->collectionListByUser(substr($principalUri, 17), 'EC');
		// construct collection objects list
		$list = [];
		foreach ($collections as $entry) {
			$list[] = new Collection($this->_store, $entry['id'], $entry['uid'], $entry['uuid'], $entry['label'], $entry['color']);
		}
		// return collection objects list
		return $list;

	}

	/**
	 * @inheritDoc
	 */
	public function hasAddressBookInAddressBookHome(string $principalUri, string $calendarUri): bool {

		return $this->_store->collectionConfirmByUUID(substr($principalUri, 17), $calendarUri);

	}

	/**
	 * @inheritDoc
	 */
	public function getAddressBookInAddressBookHome(string $principalUri, string $calendarUri): ?ExternalAddressBook {

		$entry = $this->_store->collectionFetchByUUID(substr($principalUri, 17), $calendarUri);

		if (isset($entry)) {
			return new Collection($this->_store, $entry['id'], $entry['uid'], $entry['uuid'], $entry['label'], $entry['color']);
		}
		else {
			return null;
		}

	}

}
