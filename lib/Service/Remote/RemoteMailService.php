<?php
//declare(strict_types=1);

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

use JmapClient\Client;
use JmapClient\Requests\Mail\MailboxGet;
use JmapClient\Requests\Mail\MailboxSet;
use JmapClient\Requests\Mail\MailboxQuery;

class RemoteMailService {

	protected Client $dataStore;

	public function __construct () {

	}

	public function initialize(Client $dataStore) {

		$this->dataStore = $dataStore;

	}

	public function collections(string $location, string $scope): mixed {
		// construct get request
		$r0 = new MailboxGet('ce');
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collections information
		return $response->objects();
	}

	/**
     * retrieve properties for specific collection
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionFetch(string $location, string $id): mixed {
		// construct get request
		$r0 = new MailboxGet('ce');
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return $response->objects();
    }

	/**
     * create collection in remote storage
     * 
     * @since Release 1.0.0
	 * 
	 */
	public function collectionCreate(string $location, string $label): string {
		// construct set request
		$r0 = new MailboxSet('ce');
		// construct object
		$m0 = $r0->create('1');
		$m0->in($location);
		$m0->label($label);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->created()['1']['id'];
    }

    /**
     * update collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionUpdate(string $location, string $id, string $label): string {
        // construct set request
		$r0 = new MailboxSet('ce');
		// construct object
		$m0 = $r0->update($id);
		$m0->label($label);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return isset($response->updated()[$id]) ? (string) $id : '';
    }

    /**
     * delete collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionDelete(string $location, string $id): string {
        // construct set request
		$r0 = new MailboxSet('ce');
		// construct object
		$m0 = $r0->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->deleted()[0];
    }

	/**
     * move collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionMove(string $sourceLocation, string $id, string $destinationLocation): string {
        // construct set request
		$r0 = new MailboxSet('ce');
		// construct object
		$m0 = $r0->update($id);
		$m0->in($destinationLocation);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->updated()[0];
    }

	/**
     * search for collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionSearch(string $location, string $filter, string $scope): array {
        // construct set request
		$r0 = new MailboxQuery('ce');
		// set location constraint
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// set name constraint
		if (!empty($filter)) {
			$r0->filter()->Name($filter);
		}
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return $response->list();
    }

    /**
	 * retrieve alteration for specific collection
     * 
     * @since Release 1.0.0
	 * 
     * @param string $cid		Collection Id
	 * @param string $cst		Collections Synchronization Token
	 * 
	 * @return object
	 */
	public function reconcileCollection(string $cid, string $cst): ?object {

    }

	/**
     * retrieve collection entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 * @param string $cid			Collection ID
     * @param string $cst           Collection Signature Token
	 * @param string $eid			Entity ID
	 * 
	 * @return Object       	Object on success / Null on failure
	 */
	public function fetchEntity(string $cid, string &$cst, string $eid): ?Object {

    }
    
	/**
     * create collection entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 * @param string $cid			Collection Id
	 * @param string $cst			Collection Synchronization Token
     * @param Object $so     	Source Object
	 * 
	 * @return Object        	Object on success / Null on failure
	 */
	public function createEntity(string $cid, string &$cst, Object $so): ?Object {

    }

    /**
     * update collection entity in remote storage
     * 
     * @since Release 1.0.0
     * 
     * @param string $cid			Collection ID
	 * @param string $cst			Collection Signature Token
     * @param string $eid           Entity ID
     * @param Object $so     	Source Object
	 * 
	 * @return Object        	Object on success / Null on failure
	 */
	public function updateEntity(string $cid, string &$cst, string $eid, Object $so): ?Object {

    }
    
    /**
     * delete collection entity in remote storage
     * 
     * @since Release 1.0.0
     * 
     * @param string $cid			Collection Id
	 * @param string $cst			Collection Synchronization Token
	 * @param string $eid			Entity Id
	 * 
	 * @return bool                 True on success / False on failure
	 */
    public function deleteEntity(string $cid, string $cst, string $eid): bool {
        
    }

	/**
     * retrieve collection entity attachment from remote storage
     * 
     * @since Release 1.0.0
     * 
     * @param array $batch		Batch of Attachment ID's
	 * 
	 * @return array
	 */
	public function fetchAttachment(array $batch): array {

	}

    /**
     * create collection item attachment in local storage
     * 
     * @since Release 1.0.0
     * 
	 * @param string $aid - Affiliation ID
     * @param array $sc - Collection of AttachmentObject(S)
	 * 
	 * @return string
	 */
	public function createAttachment(string $aid, array $batch): array {

    }

    /**
     * delete collection item attachment from local storage
     * 
     * @since Release 1.0.0
     * 
     * @param string $aid - Attachment ID
	 * 
	 * @return bool true - successfully delete / False - failed to delete
	 */
	public function deleteAttachment(array $batch): array {

    }

}
