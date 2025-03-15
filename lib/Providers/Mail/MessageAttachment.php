<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Providers\Mail;

/**
 * Mail Attachment Object
 *
 * This object is used to define the parameters of a mail attachment
 *
 * @since 30.0.0
 *
 */
class MessageAttachment implements \OCP\Mail\Provider\IAttachment {

	protected MessagePart $_meta;
	protected ?string $_contents = null;

	public function __construct(?MessagePart $meta = null, ?string $contents = null) {
		// determine if meta data exists
		// if meta data is missing create new
		if ($meta === null) {
			$meta = new MessagePart();
			$meta->setDisposition('attachment');
			$meta->setType('application/octet-stream');
		}
		$this->setParameters($meta);
		// determine if attachment contents exists
		// if contents exists set the contents
		if ($contents !== null) {
			$this->setContents($contents);
		}
	}

	/**
	 * sets the attachments parameters
	 *
	 * @since 1.0.0
	 *
	 * @param array $value collection of all message parameters
	 *
	 * @return self return this object for command chaining
	 */
	public function setParameters(?MessagePart $meta): self {
		
		// replace meta data store
		$this->_meta = $meta;
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
	public function getParameters(): MessagePart {
		// evaluate if data store field exists and return value(s) or null otherwise
		return $this->_meta;
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
		return $this->_meta->getBlobId();
	}

	/**
	 * sets the attachment file name
	 *
	 * @since 30.0.0
	 *
	 * @param string $value file name (e.g example.txt)
	 *
	 * @return self return this object for command chaining
	 */
	public function setName(string $value): self {
		$this->_meta->setName($value);
		return $this;
	}

	/**
	 * gets the attachment file name
	 *
	 * @since 30.0.0
	 *
	 * @return string | null returns the attachment file name or null if not set
	 */
	public function getName(): ?string {
		return $this->_meta->getName();
	}

	/**
	 * sets the attachment mime type
	 *
	 * @since 30.0.0
	 *
	 * @param string $value mime type (e.g. text/plain)
	 *
	 * @return self return this object for command chaining
	 */
	public function setType(string $value): self {
		$this->_meta->setType($value);
		return $this;
	}

	/**
	 * gets the attachment mime type
	 *
	 * @since 30.0.0
	 *
	 * @return string | null returns the attachment mime type or null if not set
	 */
	public function getType(): ?string {
		return $this->_meta->getType();
	}

	/**
	 * sets the attachment contents (actual data)
	 *
	 * @since 30.0.0
	 *
	 * @param string $value binary contents of file
	 *
	 * @return self return this object for command chaining
	 */
	public function setContents(string $value): self {
		$this->_contents = $value;
		return $this;
	}

	/**
	 * gets the attachment contents (actual data)
	 *
	 * @since 30.0.0
	 *
	 * @return string | null returns the attachment contents or null if not set
	 */
	public function getContents(): ?string {
		return $this->_contents;
	}

	/**
	 * sets the embedded status of the attachment
	 *
	 * @since 30.0.0
	 *
	 * @param bool $value true - embedded / false - not embedded
	 *
	 * @return self return this object for command chaining
	 */
	public function setEmbedded(bool $value): self {
		if ($value) {
			$this->setDisposition('inline');
		} else {
			$this->setDisposition('attachment');
		}
		return $this;
	}

	/**
	 * gets the embedded status of the attachment
	 *
	 * @since 30.0.0
	 *
	 * @return bool embedded status of the attachment
	 */
	public function getEmbedded(): bool {
		if ($this->getDisposition() === 'inline') {
			return true;
		} else {
			return false;
		}
	}

}
