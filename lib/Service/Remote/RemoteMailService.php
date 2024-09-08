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
use JmapClient\Requests\Identity\IdentityGet;
use JmapClient\Requests\Mail\MailboxGet;
use JmapClient\Requests\Mail\MailboxSet;
use JmapClient\Requests\Mail\MailboxQuery;
use JmapClient\Requests\Mail\MailGet;
use JmapClient\Requests\Mail\MailSet;
use JmapClient\Requests\Mail\MailQuery;
use JmapClient\Requests\Mail\MailSubmissionSet;
use JmapClient\Requests\Mail\MailParameters;
use JmapClient\Requests\Blob\BlobGet;
use JmapClient\Requests\Blob\BlobSet;

use OCA\JMAPC\Providers\IRange;
use OCA\JMAPC\Providers\Mail\ICollection;
use OCA\JMAPC\Providers\Mail\Collection;
use OCA\JMAPC\Providers\Mail\Message;

use OCP\Mail\Provider\IMessage;
use OCP\Mail\Provider\IAttachment;

class RemoteMailService {

	protected Client $dataStore;

	protected array $defaultMailProperties = [
		"id", "blobId", "threadId", "mailboxIds", "keywords", "size",
		"receivedAt", "messageId", "inReplyTo", "references", "sender", "from",
		"to", "cc", "bcc", "replyTo", "subject", "sentAt", "hasAttachment",
		"attachments", "preview", "bodyStructure", "bodyValues"
	];

	public function __construct () {

	}

	public function initialize(Client $dataStore) {

		$this->dataStore = $dataStore;

	}

	/**
     * retrieve properties for specific collection
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionFetch(string $account, string $location, string $id): ICollection {
		// construct get request
		$r0 = new MailboxGet($account);
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return new Collection($response->object(0)->parametersRaw());
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
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
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
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

	/**
     * list of collections in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionList(string $account, string $location, string $scope): array {
		// construct set request
		$r0 = new MailboxQuery($account);
		// set location constraint
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// construct get request
		$r1 = new MailboxGet($account);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to collection objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Collection($message->parametersRaw());
		}
		// return collection of collections
		return $list;
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
		// construct get request
		$r1 = new MailboxGet($account);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to collection objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Collection($message->parametersRaw());
		}
		// return collection of collections
		return $list;
    }

	/**
     * retrieve entity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityFetch(string $account, string $location, string $id, string $particulars = 'D'): IMessage {
		// construct set request
		$r0 = new MailGet($account);
		// construct object
		$r0->target($id);
		// select properties to return
		$r0->property(...$this->defaultMailProperties);
		$r0->bodyAll(true);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return new Message($response->object(0)->parametersRaw());
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
		//$r0 = new MailSet($account);
		// construct object
		//$r0->create('1')->parametersRaw($message->getParameters())->in($location);
		// construct set request
		//$r1 = new MailSet($account);
		// construct object
		//$r1->delete($id);
		// construct set request
		$r0 = new MailSet($account);
		// construct object
		$messageData = $message->getParameters();
		$messageData['id'] = $id;
		$r0->update($id)->parametersRaw($messageData);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
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
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
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
     * send entity
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entitySend(string $account, string $identity, IMessage $message, string $presendLocation = null, string $postsendLocation = null): string {
		
		// determine if pre-send location is present
		if ($presendLocation === null || empty($presendLocation)) {
			throw new Exception("Pre-Send Location is missing", 1);
		}
		// determine if post-send location is present
		if ($postsendLocation === null || empty($postsendLocation)) {
			throw new Exception("Post-Send Location is missing", 1);
		}
		// determine if we have the basic required data and fail otherwise
		if (empty($message->getFrom())) {
			throw new Exception("Missing Requirements: Message MUST have a From address", 1);
		}
		if (empty($message->getTo())) {
			throw new Exception("Missing Requirements: Message MUST have a To address(es)", 1);
		}
		
		// determine if message has attachments
		if (count($message->getAttachments()) > 0) {
			// process attachments first
			$message = $this->depositAttachmentsFromMessage($account, $message);
		}
		// convert from address object to string
		$from = $message->getFrom()->getAddress();
		// convert to, cc and bcc address object arrays to single strings array
		$to = array_map(
			function($entry) { return $entry->getAddress(); }, 
			array_merge($message->getTo(), $message->getCc(), $message->getBcc())
		);
		unset($cc, $bcc);
		// construct set request
		$r0 = new MailSet($account);
		$r0->create('1')->parametersRaw($message->getParameters())->in($presendLocation);
		// construct set request
		$r1 = new MailSubmissionSet($account);
		// construct envelope
		$e1 = $r1->create('2');
		$e1->identity($identity);
		$e1->message('#1');
		$e1->from($from);
		$e1->to($to);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// return collection information
		return (string) $response->created()['2']['id'];
    }

	/**
     * retrieve entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityList(string $account, string $location, IRange $range = null, string $sort = null, string $particulars = 'D'): array {
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
		// select properties to return
		$r1->property(...$this->defaultMailProperties);
		$r1->bodyAll(true);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to message objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Message($message->parametersRaw());
		}
		// return message collection
		return $list;
    }

	/**
     * search for entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entitySearch(string $account, string $location, array $filter = null, IRange $range = null, string $sort = null, string $particulars = 'D'): array {
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
		// select properties to return
		$r1->property(...$this->defaultMailProperties);
		$r1->bodyAll(true);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to message objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Message($message->parametersRaw());
		}
		// return message collection
		return $list;
    }

	/**
     * retrieve blob/attachment from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function blobFetch(string $account, string $id): Object {

		// TODO: testing remove later
		//$data = '';
		//$this->dataStore->download($account, $id, $data);
		//return null;

		// construct get request
		$r0 = new BlobGet($account);
		// construct object
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return $response->object(0);

	}

	/**
     * deposit bolb/attachment to remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function blobDeposit(string $account, string $type, &$data): array {

		// TODO: testing remove later
		$response = $this->dataStore->upload($account, $type, $data);
		// convert response to object
		$response = json_decode($response, true);

		return  $response;

		/*
		// construct set request
		$r0 = new BlobSet($account);
		// construct object
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return $response->object(0);
		*/

	}

	/**
     * retrieve collection entity attachment from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function depositAttachmentsFromMessage(string $account, Message $message): Message {
		
		$parameters = $message->getParameters();
		$attachments = $message->getAttachments();
		$matches = [];

		$this->findAttachmentParts($parameters['bodyStructure'], $matches);

		foreach ($attachments as $attachment) {
			$part = $attachment->getParameters();
			if (isset($matches[$part->getId()])) {
				// deposit attachment in data store
				$response = $this->blobDeposit($account, $part->getType(), $attachment->getContents());
				// transfer blobId and size to mail part
				$matches[$part->getId()]->blobId = $response['blobId'];
				$matches[$part->getId()]->size = $response['size'];
				unset($matches[$part->getId()]->partId);
			}
		}

		return new Message($parameters);
		
	}

	protected function findAttachmentParts(object &$part, array &$matches) {

		if ($part->disposition === 'attachment' || $part->disposition === 'inline') {
			$matches[$part->partId] = $part;
		}

		foreach ($part->subParts as $entry) {
			$this->findAttachmentParts($entry, $matches);
		}
		
	}

    /**
     * create collection item attachment in local storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function createAttachment(string $account, IAttachment ...$attachment): array {
		
		// http://LAPTOP-7DVOR6NC:8080/jmap/upload/{accountId}/


    }

    /**
     * delete collection item attachment from local storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function deleteAttachment(string $account, string ...$id): array {

    }

	/**
     * retrieve identity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function identityFetch(string $account): array {
		// construct set request
		$r0 = new IdentityGet($account);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return $response->objects();
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
