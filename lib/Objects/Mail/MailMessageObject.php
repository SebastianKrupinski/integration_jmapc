<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Mail;

use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\IAddress;
use OCP\Mail\Provider\IAttachment;

/**
 * Mail Message Object
 *
 * This object is used to define a mail message that can be used to transfer data to a provider
 *
 * @since 1.0.0
 *
 */
class MailMessageObject implements \OCP\Mail\Provider\IMessage {

	protected array $headers = [];
	protected array $parameters = [];
	protected array $attachments = [];
	protected ?string $messageText = null;
	protected ?string $messageHtml = null;
	protected ?MailMessagePartObject $bodyContents = null;
	protected array $bodyContentsText = [];
	protected array $bodyContentsHtml = [];
	protected array $bodyContentsContainers = [];
	protected array $bodyContentsAttachments = [];

	/**
	 * convert jmap parameters collection to message object
	 *
	 * @since 1.0.0
	 *
	 * @param array $parameters jmap parameters collection
	 * @param bool $amend flag merged or replaced parameters
	 */
	public function fromJmap(array $parameters, bool $amend = false): self {

		if ($amend) {
			// merge parameters with existing ones
			$this->parameters = array_merge($this->parameters, $parameters);
		} else {
			// replace parameters store
			$this->parameters = $parameters;
		}

		// decode body structure
		if (isset($this->parameters['bodyStructure']) && !$this->parameters['bodyStructure'] instanceof MailMessagePartObject) {
			if (is_object($this->parameters['bodyStructure'])) {
				$this->parameters['bodyStructure'] = get_object_vars($this->parameters['bodyStructure']);
			}
			$this->bodyContents = (new MailMessagePartObject())->fromJmap($this->parameters['bodyStructure']);
			$this->analyzeContents();
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

		// copy parameter value
		$message = $this->parameters;
		// create / reset properties
		$message['bodyStructure'] = null;
		$message['bodyValues'] = [];
		// determine if any attachments are present
		if (count($this->attachments) > 0) {
			$message['bodyStructure'] = (object)['type' => 'multipart/mixed', 'subParts' => []];
			$rootContainer = & $message['bodyStructure'];
		}

		if (isset($rootContainer)) {
			$rootContainer->subParts[] = (object)['type' => 'multipart/alternative', 'subParts' => []];
			$messageContainer = & $rootContainer->subParts[0];
		} else {
			$message['bodyStructure'] = (object)['type' => 'multipart/alternative', 'subParts' => []];
			$rootContainer = & $message['bodyStructure'];
			$messageContainer = & $rootContainer;
		}

		// add text
		if ($this->messageText !== null) {
			$messageContainer->subParts[] = (object)['type' => 'text/plain', 'partId' => 'text'];
			$message['bodyValues']['text'] = ['isTruncated' => false, 'value' => $this->messageText];
		}

		// add html
		if ($this->messageHtml !== null) {
			$messageContainer->subParts[] = (object)['type' => 'text/html', 'partId' => 'html'];
			$message['bodyValues']['html'] = ['isTruncated' => false, 'value' => $this->messageHtml];
		}

		foreach ($this->attachments as $attachment) {
			$rootContainer->subParts[] = (object)$attachment->getParameters()->getParameters();
		}

		return $message;

	}

	/**
	 * arbitrary unique text string identifying the colleciton(s) this message is in
	 *
	 * @since 1.0.0
	 *
	 * @return string|array|null id of this message
	 */
	public function in(): string|array|null {
		return isset($this->parameters['mailboxIds']) ? array_keys($this->parameters['mailboxIds']) : null;
	}

	/**
	 * arbitrary unique text string identifying this message
	 *
	 * @since 1.0.0
	 *
	 * @return string id of this message
	 */
	public function id(): string {
		return isset($this->parameters['id']) ? $this->parameters['id'] : '';
	}

	/**
	 * number indicating the size of the message
	 *
	 * @since 1.0.0
	 *
	 * @return int size of this message
	 */
	public function size(): int {
		return isset($this->parameters['size']) ? $this->parameters['size'] : 0;
	}

	/**
	 * date and/or time indicating when the message was received
	 *
	 * @since 1.0.0
	 *
	 * @return string|null received date/time of this message
	 */
	public function received(): ?string {
		return isset($this->parameters['receivedAt']) ? $this->parameters['receivedAt'] : null;
	}

	/**
	 * date and/or time indicating when the message was sent
	 *
	 * @since 1.0.0
	 *
	 * @return string|null sent date/time of this message
	 */
	public function sent(): ?string {
		return isset($this->parameters['sentAt']) ? $this->parameters['sentAt'] : null;
	}

	/**
	 * sets the sender of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress $value sender's mail address object
	 *
	 * @return self return this object for command chaining
	 */
	public function setFrom(IAddress $value): self {
		$this->parameters['from'][0] = ['email' => $value->getAddress(), 'name' => $value->getLabel()];
		return $this;
	}

	/**
	 * gets the sender of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress|null                 	sender's mail address object
	 */
	public function getFrom(): ?IAddress {
		// evaluate if data store field exists and return value(s)
		if (isset($this->parameters['from'][0])) {
			$entry = $this->parameters['from'][0];
			$value = new Address($entry['email'], $entry['name']);
			return $value;
		}
		// otherwise return null
		return null;
	}

	/**
	 * sets the sender's reply to address of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress $value senders's reply to mail address object
	 *
	 * @return self return this object for command chaining
	 */
	public function setReplyTo(IAddress $value): self {
		// create or update field in data store with value
		$this->parameters['replyTo'][0] = ['email' => $value->getAddress(), 'name' => $value->getLabel()];
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the sender's reply to address of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress|null                     sender's reply to mail address object
	 */
	public function getReplyTo(): ?IAddress {
		// evaluate if data store field exists and return value(s)
		if (isset($this->parameters['replyTo'][0])) {
			$entry = $this->parameters['replyTo'][0];
			$value = new Address($entry['email'], $entry['name']);
			return $value;
		}
		// otherwise return null
		return null;
	}

	/**
	 * sets the recipient(s) of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress ...$value collection of or one or more mail address objects
	 *
	 * @return self return this object for command chaining
	 */
	public function setTo(IAddress ...$value): self {
		// create or update field in data store with value
		foreach ($value as $entry) {
			$this->parameters['to'][] = ['name' => $entry->getLabel(), 'email' => $entry->getAddress()];
		}
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the recipient(s) of this message
	 *
	 * @since 1.0.0
	 *
	 * @param array<int,IAddress>          		collection of all recipient mail address objects
	 */
	public function getTo(): array {
		// evaluate if data store field exists and return value(s)
		if (isset($this->parameters['to']) && is_array($this->parameters['to'])) {
			foreach ($this->parameters['to'] as $entry) {
				$values[] = new Address(
					$entry['email'],
					$entry['name']
				);
			}
			return $values;
		}
		// otherwise return null
		return [];
	}

	/**
	 * sets the copy to recipient(s) of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress ...$value collection of or one or more mail address objects
	 *
	 * @return self return this object for command chaining
	 */
	public function setCc(IAddress ...$value): self {
		// create or update field in data store with value
		foreach ($value as $entry) {
			$this->parameters['cc'][] = ['name' => $entry->getLabel(), 'email' => $entry->getAddress()];
		}
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the copy to recipient(s) of this message
	 *
	 * @since 1.0.0
	 *
	 * @param array<int,IAddress> 				collection of all copied recipient mail address objects
	 */
	public function getCc(): array {
		// evaluate if data store field exists and return value(s)
		if (isset($this->parameters['cc']) && is_array($this->parameters['cc'])) {
			foreach ($this->parameters['cc'] as $entry) {
				$values[] = new Address(
					$entry['email'],
					$entry['name']
				);
			}
			return $values;
		}
		// otherwise return null
		return [];
	}

	/**
	 * sets the blind copy to recipient(s) of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress ...$value collection of or one or more mail address objects
	 *
	 * @return self return this object for command chaining
	 */
	public function setBcc(IAddress ...$value): self {
		// create or update field in data store with value
		foreach ($value as $entry) {
			$this->parameters['bcc'][] = ['name' => $entry->getLabel(), 'email' => $entry->getAddress()];
		}
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the blind copy to recipient(s) of this message
	 *
	 * @since 1.0.0
	 *
	 * @param array<int,IAddress>|null          collection of all blind copied recipient mail address objects
	 */
	public function getBcc(): array {
		// evaluate if data store field exists and return value(s)
		if (isset($this->parameters['bcc']) && is_array($this->parameters['bcc'])) {
			foreach ($this->parameters['bcc'] as $entry) {
				$values[] = new Address(
					$entry['email'],
					$entry['name']
				);
			}
			return $values;
		}
		// otherwise return null
		return [];
	}

	/**
	 * sets the subject of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string $value subject of mail message
	 *
	 * @return self return this object for command chaining
	 */
	public function setSubject(string $value): self {
		// create or update field in data store with value
		$this->parameters['subject'] = $value;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the subject of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                       subject of message or null if one is not set
	 */
	public function getSubject(): ?string {
		// evaluate if data store field exists and return value(s) or null otherwise
		return isset($this->parameters['subject']) ? $this->parameters['subject'] : null;
	}

	/**
	 * sets the plain text or html body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string $value text or html body of message
	 * @param bool $html html flag - true for html
	 *
	 * @return self return this object for command chaining
	 */
	public function setBody(string $value, bool $html = false): self {
		// evaluate html flag and create or update appropriate field in data store with value
		if ($html) {
			$this->setBodyHtml($value);
		} else {
			$this->setBodyPlain($value);
		}
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets either the html or plain text body of this message
	 *
	 * html body will be returned over plain text if html body exists
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                       html/plain body of this message or null if one is not set
	 */
	public function getBody(): ?string {
		// evaluate if data store field(s) exists and return value
		if ($this->messageHtml) {
			return $this->getBodyHtml();
		} elseif ($this->messageText) {
			return $this->getBodyPlain();
		}
		// return null if data fields did not exist in data store
		return null;
	}

	/**
	 * sets the html body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string $value html body of message
	 *
	 * @return self return this object for command chaining
	 */
	public function setBodyHtml(string $value): self {

		$this->messageHtml = $value;
		return $this;

	}

	/**
	 * gets the html body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                       html body of this message or null if one is not set
	 */
	public function getBodyHtml(): ?string {

		return $this->messageHtml;

	}

	/**
	 * sets the plain text body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string $value plain text body of message
	 *
	 * @return self return this object for command chaining
	 */
	public function setBodyPlain(string $value): self {

		$this->messageText = $value;
		return $this;

	}

	/**
	 * gets the plain text body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string|null						plain text body of this message or null if one is not set
	 */
	public function getBodyPlain(): ?string {

		return $this->messageText;

	}

	/**
	 * sets the contents of this message
	 *
	 * @since 1.0.0
	 *
	 * @param MailMessagePartObject						collection of or one or more mail attachment objects
	 *
	 * @return self return this object for command chaining
	 */
	public function setContents(MailMessagePartObject $value): self {

		$this->bodyContents = $value;
		$this->analyzeContents();
		return $this;

	}

	/**
	 * gets the contents of this message
	 *
	 * @since 1.0.0
	 *
	 * @return MailMessagePartObject
	 */
	public function getContents(): MailMessagePartObject {

		return $this->bodyContents;

	}

	protected function analyzeContents() {

		// analyze all parts
		$this->analyzeContentsPart($this->bodyContents);

		// iterate through attachment mail parts and convert them to message text
		foreach ($this->bodyContentsText as $entry) {
			if (!empty($entry->getId()) && isset($this->parameters['bodyValues'][$entry->getId()])) {
				$this->messageText .= $this->parameters['bodyValues'][$entry->getId()]['value'];
			}
		}

		// iterate through attachment mail parts and convert them to message html
		foreach ($this->bodyContentsHtml as $entry) {
			if (!empty($entry->getId()) && isset($this->parameters['bodyValues'][$entry->getId()])) {
				$this->messageHtml .= $this->parameters['bodyValues'][$entry->getId()]['value'];
			}
		}

		// iterate through attachment mail parts and convert them to message attachment
		foreach ($this->bodyContentsAttachments as $entry) {
			if (!empty($entry->getId()) && isset($this->parameters['bodyValues'][$entry->getId()])) {
				$this->attachments[] = new MailMessageAttachmentObject(
					$entry,
					$this->parameters['bodyValues'][$entry->getId()]['value']
				);
			} else {
				$this->attachments[] = new MailMessageAttachmentObject(
					$entry,
					null
				);
			}
		}

	}

	protected function analyzeContentsPart(MailMessagePartObject $part) {

		if ($part->getDisposition() == 'attachment') {
			$this->bodyContentsAttachments[] = $part;
		} else {
			match ($part->getType()) {
				'multipart/mixed' => $this->bodyContentsContainers[] = $part,
				'multipart/alternative' => $this->bodyContentsContainers[] = $part,
				'text/plain' => $this->bodyContentsText[] = $part,
				'text/html' => $this->bodyContentsHtml[] = $part,
				default => ''
			};
		}

		foreach ($part->getParts() as $part) {
			$this->analyzeContentsPart($part);
		}

	}

	protected function constructContentsContainerPart(?MailMessagePartObject $part = null, string $type): MailMessagePartObject {
		// determine if part is empty and has the correct type
		// then create a new part and copy existing contents if necessary
		if ($part === null) {
			$part = new MailMessagePartObject(['type' => $type]);
		} elseif ($part !== null && $part->getType() !== $type) {
			$part = (new MailMessagePartObject(['type' => $type]))->setParts($part);
		}

		return $part;
	}

	/**
	 * generates fresh attachment
	 *
	 * @since 1.0.0
	 *
	 * @return MessageAttachment
	 */
	public function newAttachment(string $content, string $name, string $type, bool $embedded = false): MailMessageAttachmentObject {
		$part = new MailMessagePartObject();
		$part->setId(uniqid());
		if ($embedded) {
			$part->setDisposition('inline');
		} else {
			$part->setDisposition('attachment');
		}
		$part->setType($type);
		$part->setName($name);

		return new MailMessageAttachmentObject($part, $content);
	}

	/**
	 * sets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAttachment ...$value collection of or one or more mail attachment objects
	 *
	 * @return self return this object for command chaining
	 */
	public function setAttachments(IAttachment ...$value): self {
		$this->attachments = $value;
		return $this;
	}

	/**
	 * gets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,IAttachment> collection of all mail attachment objects
	 */
	public function getAttachments(): array {
		return $this->attachments;
	}

}
