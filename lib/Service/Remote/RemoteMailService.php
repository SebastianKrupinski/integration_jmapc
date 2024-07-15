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
use JmapClient\Requests\Mail\MailGet;
use JmapClient\Requests\Mail\MailSet;
use JmapClient\Requests\Mail\MailQuery;
use OCA\JMAPC\Providers\IRange;

use OCP\Mail\Provider\IMessage;

class RemoteMailService {

	protected Client $dataStore;

	public function __construct () {

	}

	public function initialize(Client $dataStore) {

		$this->dataStore = $dataStore;

	}

	public function collections(string $account, string $location, string $scope): mixed {
		// construct get request
		$r0 = new MailboxGet($account);
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
	public function collectionFetch(string $account, string $location, string $id): mixed {
		// construct get request
		$r0 = new MailboxGet($account);
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
	public function collectionCreate(string $account, string $location, string $label): string {
		// construct set request
		$r0 = new MailboxSet($account);
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
	public function collectionUpdate(string $account, string $location, string $id, string $label): string {
        // construct set request
		$r0 = new MailboxSet($account);
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
    public function collectionDelete(string $account, string $location, string $id): string {
        // construct set request
		$r0 = new MailboxSet($account);
		// construct object
		$r0->delete($id);
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
    public function collectionMove(string $account, string $sourceLocation, string $id, string $destinationLocation): string {
        // construct set request
		$r0 = new MailboxSet($account);
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
    public function collectionSearch(string $account, string $location, string $filter, string $scope): array {
        // construct set request
		$r0 = new MailboxQuery($account);
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
     * retrieve entity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityFetch(string $account, string $location, string $id): object {
		// construct set request
		$r0 = new MailGet($account);
		// construct object
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return $response->object(0);
    }
    
	/**
     * create entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityCreate(string $account, string $location, IMessage $message): string {
		// construct set request
		$r0 = new MailSet($account);
		// construct object
		$r0->create('1')->parametersRaw($message->getParameters())->in($location);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->created()['1']['id'];
    }

    /**
     * update entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityUpdate(string $account, string $location, string $id, IMessage $message): string {
		//
		//TODO: Replace this code with an actual property update instead of replacement
		//
		// construct set request
		$r0 = new MailSet($account);
		// construct object
		$r0->create('1')->parametersRaw($message->getParameters())->in($location);
		// construct set request
		$r1 = new MailSet($account);
		// construct object
		$r1->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->created()['1']['id'];
    }
    
    /**
     * delete entity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityDelete(string $account, string $location, string $id): string {
        // construct set request
		$r0 = new MailSet($account);
		// construct object
		$r0->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->deleted()[0];
    }

	/**
     * copy entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityCopy(string $account, string $sourceLocation, string $id, string $destinationLocation): string {
        
    }

	/**
     * move entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityMove(string $account, string $sourceLocation, string $id, string $destinationLocation): string {
        // construct set request
		$r0 = new MailSet($account);
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
     * forward entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityForward(string $account, string $location, string $id, IMessage $message): string {

    }

	/**
     * reply to entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityReply(string $account, string $location, string $id, IMessage $message): string {

    }

	/**
     * send entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entitySend(string $account, IMessage $message): string {

		

    }

	/**
     * retrieve entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityList(string $account, string $location, IRange $range = null, string $sort = null): array {
		// construct query request
		$r0 = new MailQuery($account);
		// set location constraint
		$r0->filter()->in($location);
		// set range constraint
		if ($range !== null) {
			if ($range->type()->value === 'absolute') {
				$r0->startAbsolute($range->getStart())->limitAbsolute($range->getCount());
			}
			if ($range->type()->value === 'relative') {
				$r0->startRelative($range->getStart())->limitRelative($range->getCount());
			}
		}
		// set sort
		if ($sort !== null) {
			match($sort) {
				'received' => $r0->sort()->received(),
				'sent' => $r0->sort()->sent(),
				'from' => $r0->sort()->from(),
				'to' => $r0->sort()->to(),
				'subject' => $r0->sort()->subject(),
				'size' => $r0->sort()->size(),
			};
		}
		// construct get request
		$r1 = new MailGet($account);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// return collection information
		return $response->objects();
    }

	/**
     * search for entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entitySearch(string $account, string $location, array $filter = null, IRange $range = null, string $sort = null, string $scope = null): array {
		// construct query request
		$r0 = new MailQuery($account);
		// set location constraint
		$r0->filter()->in($location);
		// set filter constraints
		if (!empty($filter)) {
			// extract request filter
			$rf = $r0->filter();
			// iterate filter values
			foreach ($filter as $key => $value) {
				if (method_exists($rf, $key)) {
					$rf->$key($value);
				}
			}
		}
		// set range constraint
		if ($range !== null) {
			if ($range->type()->value === 'absolute') {
				$r0->startAbsolute($range->getStart())->limitAbsolute($range->getCount());
			}
			if ($range->type()->value === 'relative') {
				$r0->startRelative($range->getStart())->limitRelative($range->getCount());
			}
		}
		// set sort
		if ($sort !== null) {
			match($sort) {
				'received' => $r0->sort()->received(),
				'sent' => $r0->sort()->sent(),
				'from' => $r0->sort()->from(),
				'to' => $r0->sort()->to(),
				'subject' => $r0->sort()->subject(),
				'size' => $r0->sort()->size(),
			};
		}
		// construct get request
		$r1 = new MailGet($account);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// return collection information
		return $response->objects();
    }

	/**
     * retrieve collection entity attachment from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function fetchAttachment(array $batch): array {

	}

    /**
     * create collection item attachment in local storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function createAttachment(string $aid, array $batch): array {

    }

    /**
     * delete collection item attachment from local storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function deleteAttachment(array $batch): array {

    }

	// Recursive function to build JSON Pointer
    function convertJsonToJsonPointer($data, $path = '') {
        $pointer = [];
        foreach ($data as $key => $value) {
			// generate path
			if (!empty($path)) {
				$current_path = $path . '/' . str_replace('~', '~0', str_replace('/', '~1', $key));
			} else {
				$current_path = str_replace('~', '~0', str_replace('/', '~1', $key));
			}
    
            if (is_array($value)) {
                $pointer = array_merge($pointer, $this->convertJsonToJsonPointer($value, $current_path));
            } else {
                $pointer[$current_path] = $value;
            }
        }
        return $pointer;
    }

}
