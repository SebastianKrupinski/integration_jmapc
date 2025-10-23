<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Mail;

use OCA\JMAPC\Objects\Common\ICollectionObject;

/**
 * Mail Collection Object
 *
 * This object is used to define a mail collection that can be used to transfer data to a provider
 *
 * @since 1.0.0
 */
class MailCollectionObject implements ICollectionObject {

	protected array $parameters = [];

	/**
	 * convert jmap parameters collection to collection object
	 *
	 * @since 1.0.0
	 *
	 * @param array $parameters jmap parameters collection
	 * @param bool $amend flag merged or replaced parameters
	 *
	 * @return self return this object for command chaining
	 */
	public function fromJmap(array $parameters, bool $amend = false): self {
		
		if ($amend) {
			// merge parameters with existing ones
			$this->parameters = array_merge($this->parameters, $parameters);
		} else {
			// replace parameters store
			$this->parameters = $parameters;
		}
		return $this;

	}

	/**
	 * convert message object to jmap parameters array
	 *
	 * @since 1.0.0
	 *
	 * @return array collection of all message parameters
	 */
	public function toJmap(): array {
		return $this->parameters;
	}

	/**
	 * arbitrary unique text string identifying this message
	 *
	 * @since 1.0.0
	 *
	 * @return string id of this message
	 */
	public function id(): string {
		// return id of message
		return isset($this->parameters['id']) ? $this->parameters['id'] : '';
	}

	/**
	 * arbitrary unique text string identifying the colleciton this collection is nested in
	 *
	 * @since 1.0.0
	 *
	 * @return string|null id of this message
	 */
	public function in(): ?string {
		// return id of collection
		return isset($this->parameters['parentId']) ? $this->parameters['parentId'] : null;
	}

	/**
	 * number indicating the total amount of read and unread messages in the collection
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function messageTotal(): int {
		// return size of message
		return isset($this->parameters['totalEmails']) ? $this->parameters['totalEmails'] : 0;
	}

	/**
	 * number indicating the total amount of unread messages in the collection
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function messageUnread(): int {
		// return size of message
		return isset($this->parameters['unreadEmails']) ? $this->parameters['unreadEmails'] : 0;
	}

	/**
	 * number indicating the total amount of read and unread threads in the collection
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function threadsTotal(): int {
		// return size of message
		return isset($this->parameters['totalThreads']) ? $this->parameters['totalThreads'] : 0;
	}

	/**
	 * number indicating the total amount of unread threads in the collection
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function theadsUnread(): int {
		// return size of message
		return isset($this->parameters['unreadThreads']) ? $this->parameters['unreadThreads'] : 0;
	}

	/**
	 * sets the name/label of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param string $value name/label of collection
	 *
	 * @return self return this object for command chaining
	 */
	public function setLabel(string $value): self {
		// create or update field in data store with value
		$this->parameters['name'] = $value;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the name/label of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                       name/label of collection or null if one is not set
	 */
	public function getLabel(): ?string {
		// evaluate if data store field exists and return value(s) or null otherwise
		return isset($this->parameters['name']) ? $this->parameters['name'] : null;
	}

	/**
	 * sets the role of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param string $value role of collection
	 *
	 * @return self return this object for command chaining
	 */
	public function setRole(string $value): self {
		// create or update field in data store with value
		$this->parameters['role'] = $value;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the role of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                       role of collection or null if one is not set
	 */
	public function getRole(): ?string {
		// evaluate if data store field exists and return value(s) or null otherwise
		return isset($this->parameters['role']) ? $this->parameters['role'] : null;
	}

	/**
	 * sets the rank/order/priority of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param int $value rank/order/priority of collection (low number has higher priority)
	 *
	 * @return self return this object for command chaining
	 */
	public function setRank(int $value): self {
		// create or update field in data store with value
		$this->parameters['sortOrder'] = $value;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the rank/order/priority of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param int                     			rank/order/priority of collection (low number has higher priority)
	 */
	public function getRank(): int {
		// evaluate if data store field exists and return value(s) or null otherwise
		return isset($this->parameters['sortOrder']) ? $this->parameters['sortOrder'] : 0;
	}

	/**
	 * sets the subscribed state of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param bool $value subscribed state of collection
	 *
	 * @return self return this object for command chaining
	 */
	public function setSubscription(bool $value): self {
		// create or update field in data store with value
		$this->parameters['isSubscribed'] = $value;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the subscribed state of this collection
	 *
	 * @since 1.0.0
	 *
	 * @param bool                     			subscribed state of collection
	 */
	public function getSubscription(): bool {
		return isset($this->parameters['isSubscribed']) ? $this->parameters['isSubscribed'] : false;
	}

}
