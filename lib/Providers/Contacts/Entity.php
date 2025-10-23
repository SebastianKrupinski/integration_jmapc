<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Contacts;

use OCA\ContactsService\Interfaces\Entity\IEntityBase;

class Entity implements IEntityBase {

	private ?string $entityIn = null;
	private ?string $entityId = null;
	private ?string $entityLabel = null;
	private ?string $entityDescription = null;
	private ?int $entityPriority = null;
	private ?bool $entityVisibility = null;
	private ?string $entityColor = null;
	private ?string $entitySignature = null;

	public function __construct() {

	}

	public function loadData(string $userId): self {
		$this->entityId = $userId;
		return $this;
	}

	public function jsonSerialize(): mixed {
		return [
			'id' => $this->entityId,
			'label' => $this->entityLabel,
			'description' => $this->entityDescription,
			'priority' => $this->entityPriority,
			'visibility' => $this->entityVisibility,
			'color' => $this->entityColor,
			'signature' => $this->entitySignature,
		];
	}

	/**
	 * Unique arbitrary text string identifying the collection this entity belongs to (e.g. 1 or Collection1 or anything else)
	 *
	 * @since 2025.05.01
	 */
	public function in(): string {
		return (string)$this->entityIn;
	}

	/**
	 * Unique arbitrary text string identifying this service (e.g. 1 or Entity or anything else)
	 *
	 * @since 2025.05.01
	 */
	public function id(): string {
		return $this->entityId;
	}

	/**
	 * Gets the signature of this entity
	 *
	 * @since 2025.05.01
	 */
	public function signature(): ?string {
		return $this->entitySignature;
	}

	/**
	 * Gets the human friendly name of this entity (e.g. Personal Contacts)
	 *
	 * @since 2025.05.01
	 */
	public function getLabel(): ?string {
		return $this->entityLabel;
	}

	/**
	 * Gets the human friendly description of this entity
	 *
	 * @since 2025.05.01
	 */
	public function getDescription(): ?string {
		return $this->entityDescription;
	}

	/**
	 * Gets the priority of this entity
	 *
	 * @since 2025.05.01
	 */
	public function getPriority(): ?int {
		return $this->entityPriority;
	}

	/**
	 * Gets the visibility of this entity
	 *
	 * @since 2025.05.01
	 */
	public function getVisibility(): ?bool {
		return $this->entityVisibility;
	}

	/**
	 * Gets the color of this entity
	 *
	 * @since 2025.05.01
	 */
	public function getColor(): ?string {
		return $this->entityColor;
	}

}
