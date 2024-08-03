<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Providers\Mail;

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
class Message implements \OCP\Mail\Provider\IMessage {

	protected string $bodyPlainId = '1';
	protected string $bodyHtmlId = '2';
	protected bool $bodyPlainStatus = false;
	protected bool $bodyHtmlStatus = false;

	/**
	 * initialize the mail message object
	 *
	 * @since 1.0.0
	 *
	 * @param array $data						message data array
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
	 * @return string						    id of this message
	 */
	public function id(): string {
		// return id of message
		return isset($this->data['id']) ? $this->data['id'] : '';
	}

	/**
	 * arbitrary unique text string identifying the colleciton(s) this message is in
	 *
	 * @since 1.0.0
	 *
	 * @return string|array|null			    id of this message
	 */
	public function in(): string | array | null {
		// return id of message
		return isset($this->data['mailboxIds']) ? array_keys($this->data['mailboxIds']) : null;
	}

	/**
	 * number indicating the size of the message
	 *
	 * @since 1.0.0
	 *
	 * @return int						    	size of this message
	 */
	public function size(): int {
		// return size of message
		return isset($this->data['size']) ? $this->data['size'] : 0;
	}

	/**
	 * date and/or time indicating when the message was received
	 *
	 * @since 1.0.0
	 *
	 * @return string|null					    received date/time of this message
	 */
	public function received(): string | null {
		// return received date of message
		return isset($this->data['receivedAt']) ? $this->data['receivedAt'] : null;
	}

	/**
	 * date and/or time indicating when the message was sent
	 *
	 * @since 1.0.0
	 *
	 * @return string|null				    	sent date/time of this message
	 */
	public function sent(): string | null {
		// return sent date of message
		return isset($this->data['sentAt']) ? $this->data['sentAt'] : null;
	}

	/**
	 * sets the sender of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress $value		            sender's mail address object
	 *
	 * @return self                         	return this object for command chaining
	 */
	public function setFrom(IAddress $value): self {
		// create or update field in data store with value
		$this->data['from'][0] = ['email' => $value->getAddress(), 'name' => $value->getLabel()];
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the sender of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAddress|null                 	sender's mail address object
	 */
	public function getFrom(): IAddress | null {
		// evaluate if data store field exists and return value(s)
		if (isset($this->data['from'][0])) {
			$entry = $this->data['from'][0];
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
	 * @param IAddress $value		            senders's reply to mail address object
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setReplyTo(IAddress $value): self {
		// create or update field in data store with value
		$this->data['replyTo'][0] = ['email' => $value->getAddress(), 'name' => $value->getLabel()];
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
	public function getReplyTo(): IAddress | null {
		// evaluate if data store field exists and return value(s)
		if (isset($this->data['replyTo'][0])) {
			$entry = $this->data['replyTo'][0];
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
	 * @param IAddress ...$value		        collection of or one or more mail address objects
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setTo(IAddress ...$value): self {
		// create or update field in data store with value
		foreach ($value as $entry) {
			$this->data['to'][] = ['name' => $entry->getLabel(), 'email' => $entry->getAddress()];
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
		if (isset($this->data['to']) && is_array($this->data['to'])) {
			foreach ($this->data['to'] as $entry) {
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
	 * @param IAddress ...$value		        collection of or one or more mail address objects
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setCc(IAddress ...$value): self {
		// create or update field in data store with value
		foreach ($value as $entry) {
			$this->data['cc'][] = ['name' => $entry->getLabel(), 'email' => $entry->getAddress()];
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
		if (isset($this->data['cc']) && is_array($this->data['cc'])) {
			foreach ($this->data['cc'] as $entry) {
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
	 * @param IAddress ...$value		        collection of or one or more mail address objects
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setBcc(IAddress ...$value): self {
		// create or update field in data store with value
		foreach ($value as $entry) {
			$this->data['bcc'][] = ['name' => $entry->getLabel(), 'email' => $entry->getAddress()];
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
		if (isset($this->data['bcc']) && is_array($this->data['bcc'])) {
			foreach ($this->data['bcc'] as $entry) {
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
	 * @param string $value                     subject of mail message
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setSubject(string $value): self {
		// create or update field in data store with value
		$this->data['subject'] = $value;
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
	public function getSubject(): string | null {
		// evaluate if data store field exists and return value(s) or null otherwise
		return isset($this->data['subject']) ? $this->data['subject'] : null;
	}

	/**
	 * sets the plain text or html body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string $value                     text or html body of message
	 * @param bool $html                        html flag - true for html
	 *
	 * @return self                             return this object for command chaining
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
	public function getBody(): string | null {
		// evaluate if data store field(s) exists and return value
		if ($this->bodyHtmlStatus) {
			return $this->getBodyHtml();
		} elseif ($this->bodyPlainStatus) {
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
	 * @param string $value                     html body of message
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setBodyHtml(string $value): self {
		// create or update field(s) in data store with value
		$this->data['bodyStructure']['type'] = 'multipart/alternative';
		$this->data['bodyStructure']['subParts'][] = ['partId' => $this->bodyHtmlId, 'type' => 'text/html'];		
		$this->data['bodyValues'][$this->bodyHtmlId] = ['value' => $value, 'isTruncated' => false];
		$this->bodyPlainStatus = true;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the html body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                       html body of this message or null if one is not set
	 */
	public function getBodyHtml(): string | null {
		// evaluate if data store field exists and return value(s) or null otherwise
		return $this->bodyHtmlStatus ? $this->data['bodyValues'][$this->bodyHtmlId]['value'] : null;
	}

	/**
	 * sets the plain text body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string $value         			plain text body of message
	 *
	 * @return self                 			return this object for command chaining
	 */
	public function setBodyPlain(string $value): self {
		// create or update field(s) in data store with value
		$this->data['bodyStructure']['type'] = 'multipart/alternative';
		$this->data['bodyStructure']['subParts'][] = ['partId' => $this->bodyPlainId, 'type' => 'text/plain'];		
		$this->data['bodyValues'][$this->bodyPlainId] = ['value' => $value, 'isTruncated' => false];
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the plain text body of this message
	 *
	 * @since 1.0.0
	 *
	 * @param string|null						plain text body of this message or null if one is not set
	 */
	public function getBodyPlain(): string | null {
		// evaluate if data store field exists and return value(s) or null otherwise
		return $this->bodyPlainStatus ? $this->data['bodyValues'][$this->bodyPlainId]['value'] : null;
	}

	/**
	 * sets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @param IAttachment ...$value				collection of or one or more mail attachment objects
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setAttachments(IAttachment ...$value): self {
		// create or update field in data store with value
		$this->data['attachments'] = $value;
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,IAttachment>		    collection of all mail attachment objects
	 */
	public function getAttachments(): array {
		// evaluate if data store field exists and return value(s) or null otherwise
		return isset($this->data['attachments']) ? $this->data['attachments'] : [];
	}

	/**
	 * sets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @param array $value						collection of all message parameters
	 *
	 * @return self                             return this object for command chaining
	 */
	public function setParameters(array $data): self {
		// replace parameters store
		$this->data = $data;
		// decode body structure
		if (isset($data['bodyStructure']) && isset($data['bodyStructure']['subParts'])) {
			foreach ($data['bodyStructure']['subParts'] as $entry) {
				if ($entry['type'] === 'text/plain') {
					$this->bodyPlainStatus = true;
					$this->bodyPlainId = $entry['partId'];
				}
				elseif ($entry['type'] === 'text/html') {
					$this->bodyHtmlStatus = true;
					$this->bodyHtmlId = $entry['partId'];
				}
			}
		}
		elseif (isset($data['bodyStructure']) && isset($data['bodyStructure']['partId'])) {
			if ($data['bodyStructure']['type'] === 'text/plain') {
				$this->bodyPlainStatus = true;
				$this->bodyPlainId = $data['bodyStructure']['partId'];
			}
			elseif ($data['bodyStructure']['type'] === 'text/html') {
				$this->bodyHtmlStatus = true;
				$this->bodyHtmlId = $data['bodyStructure']['partId'];
			}
		}
		
		if (isset($data['textBody'])) {
			$this->bodyHtmlStatus = true;
			$this->bodyHtmlId = $data['textBody'][0]['partId'];
		}

		if (isset($data['htmlBody'])) {
			$this->bodyHtmlStatus = true;
			$this->bodyHtmlId = $data['htmlBody'][0]['partId'];
		}
		// return this object for command chaining
		return $this;
	}

	/**
	 * gets the attachments of this message
	 *
	 * @since 1.0.0
	 *
	 * @return array					collection of all message parameters
	 */
	public function getParameters(): array {
		// evaluate if data store field exists and return value(s) or null otherwise
		return (isset($this->data)) ? $this->data : [];
	}

}
