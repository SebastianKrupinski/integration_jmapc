<?php

declare(strict_types=1);

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

use Exception;
use JmapClient\Client;
use JmapClient\Requests\Blob\BlobGet;
use JmapClient\Requests\Blob\BlobSet;
use JmapClient\Requests\Mail\MailboxGet;
use JmapClient\Requests\Mail\MailboxParameters as MailboxParametersRequest;
use JmapClient\Requests\Mail\MailboxQuery;
use JmapClient\Requests\Mail\MailboxSet;
use JmapClient\Requests\Mail\MailGet;
use JmapClient\Requests\Mail\MailIdentityGet;
use JmapClient\Requests\Mail\MailParameters as MailParametersRequest;
use JmapClient\Requests\Mail\MailQuery;
use JmapClient\Requests\Mail\MailSet;
use JmapClient\Requests\Mail\MailSubmissionSet;
use JmapClient\Responses\Mail\MailboxParameters as MailboxParametersResponse;
use JmapClient\Responses\Mail\MailParameters as MailParametersResponse;
use JmapClient\Responses\ResponseException;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\Mail\MailCollectionObject;
use OCA\JMAPC\Objects\Mail\MailMessageObject;
use OCA\JMAPC\Providers\Mail\Message;
use OCA\JMAPC\Store\Common\Range\IRangeTally;
use OCA\JMAPC\Store\Common\Range\RangeAnchorType;
use OCA\JMAPC\Store\Common\Range\RangeTallyAbsolute;
use OCA\JMAPC\Store\Common\Range\RangeTallyRelative;
use OCA\JMAPC\Store\Remote\Filters\MailCollectionFilter;
use OCA\JMAPC\Store\Remote\Filters\MailMessageFilter;
use OCA\JMAPC\Store\Remote\Sort\MailCollectionSort;
use OCA\JMAPC\Store\Remote\Sort\MailObjectSort;
use OCP\Mail\Provider\IAttachment;

class RemoteMailService {
	protected Client $dataStore;
	protected string $dataAccount;

	protected ?string $resourceNamespace = null;
	protected ?string $resourceCollectionLabel = null;
	protected ?string $resourceEntityLabel = null;

	protected array $defaultMailProperties = [
		'id', 'blobId', 'threadId', 'mailboxIds', 'keywords', 'size',
		'receivedAt', 'messageId', 'inReplyTo', 'references', 'sender', 'from',
		'to', 'cc', 'bcc', 'replyTo', 'subject', 'sentAt', 'hasAttachment',
		'attachments', 'preview', 'bodyStructure', 'bodyValues'
	];

	public function __construct() {

	}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
		// evaluate if client is connected
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
		// determine account
		if ($dataAccount === null) {
			if ($this->resourceNamespace !== null) {
				$this->dataAccount = $dataStore->sessionAccountDefault($this->resourceNamespace, false);
			} else {
				$this->dataAccount = $dataStore->sessionAccountDefault('mail');
			}
		} else {
			$this->dataAccount = $dataAccount;
		}

	}

	/**
	 * list of collections in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string|null $location Id of parent collection
	 * @param MailCollectionFilter|null $filter collection filter
	 * @param MailCollectionSort|null $sort collection sort
	 *
	 * @return array<string,MailCollectionObject>
	 */
	public function collectionList(?string $location = null, ?MailCollectionFilter $filter = null, ?MailCollectionSort $sort = null): array {
		// construct request
		$r0 = new MailboxQuery($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// define location
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// define filter
		if ($filter !== null) {
			foreach ($filter->conditions() as $condition) {
				match($condition['attribute']) {
					'in' => $r0->filter()->in($condition['value']),
					'name' => $r0->filter()->name($condition['value']),
					'role' => $r0->filter()->role($condition['value']),
					'hasRoles' => $r0->filter()->hasRoles($condition['value']),
					'subscribed' => $r0->filter()->isSubscribed($condition['value']),
					default => null
				};
			}
		}
		// define order
		if ($sort !== null) {
			foreach ($sort->conditions() as $condition) {
				match($condition['attribute']) {
					'name' => $r0->sort()->name($condition['value']),
					'order' => $r0->sort()->order($condition['value']),
					default => null
				};
			}
		}
		// construct request
		$r1 = new MailboxGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// define target
		$r1->targetFromRequest($r0, '/ids');
		// transceive
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// determine if command errored
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap objects to collection objects
		$list = [];
		foreach ($response->objects() as $id => $so) {
			if (!$so instanceof MailboxParametersResponse) {
				continue;
			}
			$to = $this->toMailCollection($so);
			//$to->setSignature((string)$response->state());
			$list[$id] = $to;
		}
		// return collection of collections
		return $list;
	}

	/**
	 * fresh instance of collection filter object
	 *
	 * @since Release 1.0.0
	 */
	public function collectionListFilter(): MailCollectionFilter {
		return new MailCollectionFilter();
	}

	/**
	 * fresh instance of collection sort object
	 *
	 * @since Release 1.0.0
	 */
	public function collectionListSort(): MailCollectionSort {
		return new MailCollectionSort();
	}

	/**
	 * fresh instance of collection object
	 *
	 * @since Release 1.0.0
	 */
	public function collectionFresh(): MailCollectionObject {
		return new MailCollectionObject();
	}

	/**
	 * retrieve properties for specific collection
	 *
	 * @since Release 1.0.0
	 */
	public function collectionFetch(string $id): MailCollectionObject {
		// construct request
		$r0 = new MailboxGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target($id);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to collection object
		$so = $response->object(0);
		$to = null;
		if ($so instanceof MailboxParametersResponse) {
			$to = $this->toMailCollection($so);
			//$to->setSignature((string)$response->state());
		}
		return $to;
	}

	/**
	 * create collection in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function collectionCreate(string $location, MailCollectionObject $so): ?MailCollectionObject {
		// convert entity
		$to = $this->fromMailCollection($so);
		$to->in($location);
		// construct request
		$r0 = new MailboxSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->create('1', $to);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command succeeded
		if (array_key_exists('1', $response->created())) {
			// update entity
			$ro = $response->created()['1'];
			$so->fromJmap($ro, true);
			//$so->setSignature((string)$response->stateNew());
			return $so;
		}
		return null;
	}

	/**
	 * modify collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionModify(MailCollectionObject $so): ?MailCollectionObject {
		// extract entity id
		$id = $so->id();
		// convert entity
		$to = $this->fromMailCollection($so);
		// construct request
		$r0 = new MailboxSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($id, $to);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command succeeded
		if (array_key_exists($id, $response->updated())) {
			//$so->setSignature((string)$response->stateNew());
			return $so;
		}
		return null;
	}

	/**
	 * delete collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionDelete(string $id): ?string {
		// construct request
		$r0 = new MailboxSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->delete($id);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command succeeded
		if (array_search($id, $response->deleted()) !== false) {
			return $response->stateNew();
		}
		return null;
	}

	/**
	 * retrieve entities from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityList(?string $location = null, ?MailMessageFilter $filter = null, ?MailObjectSort $sort = null, ?IRangeTally $range = null, ?string $granularity = null): array {
		// construct request
		$r0 = new MailQuery($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// define location
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// define filter
		if ($filter !== null) {
			foreach ($filter->conditions() as $condition) {
				match($condition['attribute']) {
					'in' => $r0->filter()->in($condition['value']),
					'inOmit' => $r0->filter()->inOmit($condition['value']),
					'text' => $r0->filter()->text($condition['value']),
					'from' => $r0->filter()->from($condition['value']),
					'to' => $r0->filter()->to($condition['value']),
					'cc' => $r0->filter()->cc($condition['value']),
					'bcc' => $r0->filter()->bcc($condition['value']),
					'subject' => $r0->filter()->subject($condition['value']),
					'body' => $r0->filter()->body($condition['value']),
					'attachmentPresent' => $r0->filter()->hasAttachment($condition['value']),
					'tagPresent' => $r0->filter()->keywordPresent($condition['value']),
					'tagAbsent' => $r0->filter()->keywordAbsent($condition['value']),
					'receivedBefore' => $r0->filter()->receivedBefore($condition['value']),
					'receivedAfter' => $r0->filter()->receivedAfter($condition['value']),
					'sizeMin' => $r0->filter()->sizeMin((int)$condition['value']),
					'sizeMax' => $r0->filter()->sizeMax((int)$condition['value']),
					default => null
				};
			}
		}
		// define order
		if ($sort !== null) {
			foreach ($sort->conditions() as $condition) {
				match($condition['attribute']) {
					'received' => $r0->sort()->received($condition['value']),
					'sent' => $r0->sort()->sent($condition['value']),
					'from' => $r0->sort()->from($condition['value']),
					'to' => $r0->sort()->to($condition['value']),
					'subject' => $r0->sort()->subject($condition['value']),
					'size' => $r0->sort()->size($condition['value']),
					'tag' => $r0->sort()->keyword($condition['value']),
					default => null
				};
			}
		}
		// define range
		if ($range !== null) {
			if ($range->anchor() === RangeAnchorType::ABSOLUTE) {
				$r0->limitAbsolute($range->getPosition(), $range->getCount());
			}
			if ($range->anchor() === RangeAnchorType::RELATIVE) {
				$r0->limitRelative($range->getPosition(), $range->getCount());
			}
		}
		// construct get request
		$r1 = new MailGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
			if (!$message instanceof MailParametersResponse) {
				continue;
			}
			$list[$id] = $this->toMailMessage($message);
		}
		// return message collection
		return $list;
	}

	/**
	 * fresh instance of object filter
	 *
	 * @since Release 1.0.0
	 */
	public function entityListFilter(): MailMessageFilter {
		return new MailMessageFilter();
	}

	/**
	 * fresh instance of object sort
	 *
	 * @since Release 1.0.0
	 */
	public function entityListSort(): MailObjectSort {
		return new MailObjectSort();
	}

	/**
	 * fresh instance of object range
	 *
	 * @since Release 1.0.0
	 */
	public function entityListRange(RangeAnchorType $type): IRangeTally {
		if ($type === RangeAnchorType::RELATIVE) {
			return new RangeTallyRelative();
		}
		return new RangeTallyAbsolute();
	}

	/**
	 * retrieve entity from remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function entityFetch(string $id, string $granularity = 'D'): ?MailMessageObject {
		// construct request
		$r0 = new MailGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target($id);
		// select properties to return
		$r0->property(...$this->defaultMailProperties);
		$r0->bodyAll(true);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		if ($response->object(0) instanceof MailParametersResponse) {
			return $this->toMailMessage($response->object(0));
		} else {
			return null;
		}
	}

	/**
	 * fresh instance of message object
	 *
	 * @since Release 1.0.0
	 */
	public function entityFresh(): MailMessageObject {
		return new MailMessageObject();
	}

	/**
	 * create entity in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function entityCreate(string $location, MailMessageObject $so): ?MailMessageObject {
		// convert entity
		$to = $this->fromMailMessage($so);
		$to->in($location);
		// construct request
		$r0 = new MailSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->create('1', $to);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command succeeded
		if (array_key_exists('1', $response->created())) {
			// update entity
			$ro = $response->created()['1'];
			if (!isset($ro['mailboxIds'])) {
				$ro['mailboxIds'] = [$location => true];
			}
			$so->fromJmap($ro, true);
			return $so;
		}
		return null;
	}

	/**
	 * update entity in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function entityModify(MailMessageObject $so): ?MailMessageObject {
		// extract entity id
		$id = $so->id();
		// convert entity
		$to = $this->fromMailMessage($so);
		// construct request
		$r0 = new MailSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($id, $to);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command succeeded
		if (array_key_exists($id, $response->updated())) {
			// update entity
			$ro = $response->updated()[$id];
			$so->fromJmap($ro, true);
			return $so;
		}
		return null;
	}

	/**
	 * delete entity from remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function entityDelete(string $id): ?string {
		// construct set request
		$r0 = new MailSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command succeeded
		if (array_search($id, $response->deleted()) !== false) {
			return $response->stateNew();
		}
		return null;
	}

	/**
	 * copy entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityCopy(string $location, MailMessageObject $so): ?MailMessageObject {
		return null;
	}

	/**
	 * move entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityMove(string $location, MailMessageObject $so): ?MailMessageObject {
		// extract entity id
		$id = $so->id();
		// construct request
		$r0 = new MailSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($id)->in($location);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command succeeded
		if (array_key_exists($id, $response->updated())) {
			$so->fromJmap(['mailboxIds' => [$location]], true);
			return $so;
		}
		return null;
	}

	/**
	 * send entity
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entitySend(string $identity, MailMessageObject $message, ?string $presendLocation = null, ?string $postsendLocation = null): string {
		// determine if pre-send location is present
		if ($presendLocation === null || empty($presendLocation)) {
			throw new Exception('Pre-Send Location is missing', 1);
		}
		// determine if post-send location is present
		if ($postsendLocation === null || empty($postsendLocation)) {
			throw new Exception('Post-Send Location is missing', 1);
		}
		// determine if we have the basic required data and fail otherwise
		if (empty($message->getFrom())) {
			throw new Exception('Missing Requirements: Message MUST have a From address', 1);
		}
		if (empty($message->getTo())) {
			throw new Exception('Missing Requirements: Message MUST have a To address(es)', 1);
		}
		// determine if message has attachments
		if (count($message->getAttachments()) > 0) {
			// process attachments first
			$message = $this->depositAttachmentsFromMessage($message);
		}
		// convert from address object to string
		$from = $message->getFrom()->getAddress();
		// convert to, cc and bcc address object arrays to single strings array
		$to = array_map(
			function ($entry) { return $entry->getAddress(); },
			array_merge($message->getTo(), $message->getCc(), $message->getBcc())
		);
		unset($cc, $bcc);
		// construct set request
		$r0 = new MailSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->create('1', $message)->in($presendLocation);
		// construct set request
		$r1 = new MailSubmissionSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
		return (string)$response->created()['2']['id'];
	}

	/**
	 * retrieve collection entity attachment from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function depositAttachmentsFromMessage(MailMessageObject $message): MailMessageObject {

		$parameters = $message->toJmap();
		$attachments = $message->getAttachments();
		$matches = [];

		$this->findAttachmentParts($parameters['bodyStructure'], $matches);

		foreach ($attachments as $attachment) {
			$part = $attachment->toJmap();
			if (isset($matches[$part->getId()])) {
				// deposit attachment in data store
				$response = $this->blobDeposit($account, $part->getType(), $attachment->getContents());
				// transfer blobId and size to mail part
				$matches[$part->getId()]->blobId = $response['blobId'];
				$matches[$part->getId()]->size = $response['size'];
				unset($matches[$part->getId()]->partId);
			}
		}

		return (new MailMessageObject())->fromJmap($parameters);

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
	 * retrieve identity from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function identityFetch(?string $account = null): array {
		if ($account === null) {
			$account = $this->dataAccount;
		}
		// construct set request
		$r0 = new MailIdentityGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return $response->objects();
	}

	// Recursive function to build JSON Pointer
	public function convertJsonToJsonPointer($data, $path = '') {
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

	/**
	 * convert jmap mail collection to mail collection
	 *
	 * @since Release 1.0.0
	 *
	 */
	private function toMailCollection(MailboxParametersResponse $so): MailCollectionObject {
		$to = $this->collectionFresh();
		$to->fromJmap($so->parametersRaw());
		return $to;
	}

	/**
	 * convert mail collection to jmap mail collection
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromMailCollection(MailCollectionObject $so): MailboxParametersRequest {
		$to = new MailboxParametersRequest();
		$to->parametersRaw($so->toJmap());
		return $to;
	}

	/**
	 * convert jmap mail message to mail message
	 *
	 * @since Release 1.0.0
	 *
	 */
	private function toMailMessage(MailParametersResponse $so): MailMessageObject {
		$to = new MailMessageObject();
		$to->fromJmap($so->parametersRaw());
		return $to;
	}

	/**
	 * convert mail message to jmap mail message
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromMailMessage(MailMessageObject $so): MailParametersRequest {
		$to = new MailParametersRequest();
		$to->parametersRaw($so->toJmap());
		return $to;
	}

}
