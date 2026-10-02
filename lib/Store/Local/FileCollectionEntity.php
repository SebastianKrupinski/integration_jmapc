<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Store\Local;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method getId(): int
 * @method getUid(): ?string
 * @method setUid(string $uid): void
 * @method getSid(): ?int
 * @method setSid(int $sid): void
 * @method getCcid(): ?string
 * @method setCcid(?string $ccid): void
 * @method getUuid(): ?string
 * @method setUuid(string $uuid): void
 * @method getLabel(): ?string
 * @method setLabel(?string $label): void
 * @method getLocation(): ?string
 * @method setLocation(string $location): void
 * @method getMode(): ?string
 * @method setMode(string $mode): void
 * @method getVisible(): ?bool
 * @method setVisible(bool $visible): void
 */
class FileCollectionEntity extends Entity implements JsonSerializable {
	protected ?string $uid = null;
	protected ?int $sid = null;
	protected ?string $ccid = null;
	protected ?string $uuid = null;
	protected ?string $label = null;
	protected ?string $location = null;
	protected ?string $mode = null;
	protected ?bool $visible = null;

	public function __construct() {
		$this->addType('sid', 'integer');
		$this->addType('visible', 'boolean');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uid' => $this->uid,
			'sid' => $this->sid,
			'ccid' => $this->ccid,
			'uuid' => $this->uuid,
			'label' => $this->label,
			'location' => $this->location,
			'mode' => $this->mode,
			'visible' => $this->visible,
		];
	}
}
