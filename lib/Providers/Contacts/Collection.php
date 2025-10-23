<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Contacts;

use OCA\ContactsService\Interfaces\Collection\ICollectionMutable;

class Collection implements ICollectionMutable {

	private ?string $collectionId = null;
	private ?string $collectionLabel = null;
	private ?string $collectionDescription = null;
	private ?int $collectionPriority = null;
	private ?bool $collectionVisibility = null;
	private ?string $collectionColor = null;
	private bool $collectionEnabled = false;
	private ?string $collectionSignature = null;

	public function __construct() {
		
	}

	public function loadData(string $userId): self {
		$this->collectionId = $userId;
		return $this;
	}

	public function jsonSerialize(): mixed {
		return [
			'id' => $this->collectionId,
			'label' => $this->collectionLabel,
			'description' => $this->collectionDescription,
			'priority' => $this->collectionPriority,
			'visibility' => $this->collectionVisibility,
			'color' => $this->collectionColor,
			'enabled' => $this->collectionEnabled,
			'signature' => $this->collectionSignature,
		];
	}

	/**
	 * Unique arbitrary text string identifying this service (e.g. 1 or collection1 or anything else)
	 *
	 * @since 2025.05.01
	 */
	public function id(): string {
		return $this->collectionId;
	}

	/**
	 * Gets the signature of this collection
	 *
	 * @since 2025.05.01
	 */
	public function signature(): ?string {
		return $this->collectionSignature;
	}

	/**
	 * Gets the active status of this collection
	 *
	 * @since 2025.05.01
	 */
	public function getEnabled(): bool {
		return (bool)$this->collectionEnabled;
	}

	/**
	 * Sets the active status of this collection
	 *
	 * @since 2025.05.01
	 */
	public function setEnabled(bool $value): self {
		$this->collectionEnabled = $value;
		return $this;
	}

	/**
	 * Gets the human friendly name of this collection (e.g. Personal Contacts)
	 *
	 * @since 2025.05.01
	 */
	public function getLabel(): ?string	{
		return $this->collectionLabel;
	}

	/**
	 * Sets the human friendly name of this collection (e.g. Personal Contacts)
	 *
	 * @since 2025.05.01
	 */
	public function setLabel(string $value): self {
		$this->collectionLabel = $value;
		return $this;
	}

	/**
	 * Gets the human friendly description of this collection
	 *
	 * @since 2025.05.01
	 */
	public function getDescription(): ?string {
		return $this->collectionDescription;
	}

	/**
	 * Sets the human friendly description of this collection
	 *
	 * @since 2025.05.01
	 */
	public function setDescription(?string $value): self {
		$this->collectionDescription = $value;
		return $this;
	}

	/**
	 * Gets the priority of this collection
	 *
	 * @since 2025.05.01
	 */
	public function getPriority(): ?int	{
		return $this->collectionPriority;
	}

	/**
	 * Sets the priority of this collection
	 *
	 * @since 2025.05.01
	 */
	public function setPriority(?int $value): self {
		$this->collectionPriority = $value;
		return $this;
	}

	/**
	 * Gets the visibility of this collection
	 *
	 * @since 2025.05.01
	 */
	public function getVisibility(): ?bool {
		return $this->collectionVisibility;
	}

	/**
	 * Sets the visibility of this collection
	 *
	 * @since 2025.05.01
	 */
	public function setVisibility(?bool $value): self {
		$this->collectionVisibility = $value;
		return $this;
	}

	/**
	 * Gets the color of this collection
	 *
	 * @since 2025.05.01
	 */
	public function getColor(): ?string {
		return $this->collectionColor;
	}

	/**
	 * Sets the color of this collection
	 *
	 * @since 2025.05.01
	 */
	public function setColor(?string $value): self {
		$this->collectionColor = $value;
		return $this;
	}

}
