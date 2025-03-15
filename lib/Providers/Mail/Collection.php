<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Providers\Mail;

/**
 * Mail Collection Object
 *
 * This object is used to define a mail collection that can be used to transfer data to a provider
 *
 * @since 1.0.0
 *
 */
class Collection implements ICollection {

	/**
	 * initialize the mail message object
	 *
	 * @since 1.0.0
	 *
	 * @param array $data message data array
	 */
	public function __construct(
		protected array $data = [],
	) {
		$this->setParameters($data);
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
		return isset($this->data['id']) ? $this->data['id'] : '';
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
		return isset($this->data['parentId']) ? $this->data['parentId'] : null;
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
		return isset($this->data['totalEmails']) ? $this->data['totalEmails'] : 0;
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
		return isset($this->data['unreadEmails']) ? $this->data['unreadEmails'] : 0;
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
		return isset($this->data['totalThreads']) ? $this->data['totalThreads'] : 0;
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
		return isset($this->data['unreadThreads']) ? $this->data['unreadThreads'] : 0;
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
		$this->data['name'] = $value;
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
		return isset($this->data['name']) ? $this->data['name'] : null;
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
		$this->data['role'] = $value;
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
		return isset($this->data['role']) ? $this->data['role'] : null;
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
		$this->data['sortOrder'] = $value;
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
		return isset($this->data['sortOrder']) ? $this->data['sortOrder'] : 0;
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
		$this->data['isSubscribed'] = $value;
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
	public function getSubscription(): string {
		// evaluate if data store field exists and return value(s) or null otherwise
		return isset($this->data['isSubscribed']) ? $this->data['isSubscribed'] : null;
	}

	/**
	 * sets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @param array $value collection of all message parameters
	 *
	 * @return self return this object for command chaining
	 */
	public function setParameters(array $data): self {
		// replace parameters store
		$this->data = $data;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @return array collection of all message parameters
	 */
	public function getParameters(): array {
		// evaluate if data store field exists and return value(s) or null otherwise
		return (isset($this->data)) ? $this->data : [];
	}

}
