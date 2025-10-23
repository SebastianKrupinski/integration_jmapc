<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Mail;

class MailMessagePartObject {

	protected array $_parameters = [];
	protected array $_parts = [];

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
			$this->_parameters = array_merge($this->_parameters, $parameters);
		} else {
			// replace parameters store
			$this->_parameters = $parameters;
		}

		// determine if parameters contains subparts
		// if subParts exist convert them to a MessagePart object
		// and remove subParts parameter
		if (is_array($this->_parameters['subParts'])) {
			foreach ($this->_parameters['subParts'] as $key => $entry) {
				if (is_object($entry)) {
					$entry = get_object_vars($entry);
				}
				$this->_parts[$key] = (new MailMessagePartObject)->fromJmap($entry);
			}
			unset($this->_parameters['subParts']);
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
		$parameters = $this->_parameters;
		// determine if this MessagePart has any sub MessageParts
		// if sub MessageParts exist retrieve sub MessagePart parameters
		// and add them to the subParts parameters, otherwise set the subParts parameter to nothing
		if (count($this->_parts) > 0) {
			$parameters['subParts'] = [];
			foreach ($this->_parts as $entry) {
				$parameters['subParts'][] = $entry->toJmap();
			}
		} else {
			$parameters['subParts'] = null;
		}
		
		return $parameters;

	}

	public function setBlobId(string $value): self {
		$this->_parameters['blobId'] = $value;
		return $this;
	}

	public function getBlobId(): ?string {
		return $this->_parameters['blobId'] ?? null;
	}

	public function setId(string $value): self {
		$this->_parameters['partId'] = $value;
		return $this;
	}

	public function getId(): ?string {
		return $this->_parameters['partId'] ?? null;
	}

	public function setType(string $value): self {
		$this->_parameters['type'] = $value;
		return $this;
	}

	public function getType(): ?string {
		return $this->_parameters['type'] ?? null;
	}

	public function setDisposition(string $value): self {
		$this->_parameters['disposition'] = $value;
		return $this;
	}

	public function getDisposition(): ?string {
		return $this->_parameters['disposition'] ?? null;
	}

	public function setName(string $value): self {
		$this->_parameters['name'] = $value;
		return $this;
	}

	public function getName(): ?string {
		return $this->_parameters['name'] ?? null;
	}

	public function setCharset(string $value): self {
		$this->_parameters['charset'] = $value;
		return $this;
	}

	public function getCharset(): ?string {
		return $this->_parameters['charset'] ?? null;
	}

	public function setLanguage(string $value): self {
		$this->_parameters['language'] = $value;
		return $this;
	}

	public function getLanguage(): ?string {
		return $this->_parameters['language'] ?? null;
	}

	public function setLocation(string $value): self {
		$this->_parameters['location'] = $value;
		return $this;
	}

	public function getLocation(): ?string {
		return $this->_parameters['location'] ?? null;
	}

	public function setParts(MailMessagePartObject ...$value): self {
		$this->_parts = $value;
		return $this;
	}

	public function getParts(): array {
		return $this->_parts;
	}

}
