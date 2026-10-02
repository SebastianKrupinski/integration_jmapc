<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\File;

/**
 * JMAP FileNode
 */
class FileNodeObject {

	private array $parameters = [];

	/**
	 * @param array $parameters jmap parameters collection
	 */
	public function fromJmap(array $parameters): self {
		$this->parameters = $parameters;
		return $this;
	}

	public function toJmap(): array {
		return $this->parameters;
	}

	public function id(): string {
		return (string)($this->parameters['id'] ?? '');
	}

	/**
	 * id of the parent node, null for a top level node
	 */
	public function in(): ?string {
		return $this->parameters['parentId'] ?? null;
	}

	public function label(): string {
		return (string)($this->parameters['name'] ?? '');
	}

	public function isDirectory(): bool {
		if (isset($this->parameters['nodeType'])) {
			return $this->parameters['nodeType'] === 'directory';
		}
		return ($this->parameters['blobId'] ?? null) === null;
	}

	public function blob(): ?string {
		return $this->parameters['blobId'] ?? null;
	}

	public function size(): int {
		return (int)($this->parameters['size'] ?? 0);
	}

	public function type(): ?string {
		return $this->parameters['type'] ?? null;
	}

	/**
	 * modification time as unix timestamp, falls back to the creation time
	 */
	public function modified(): int {
		$value = $this->parameters['modified'] ?? $this->parameters['created'] ?? null;
		$time = is_string($value) ? strtotime($value) : false;
		return $time === false ? 0 : $time;
	}

	/**
	 * determine if the current user holds a right on this node
	 *
	 * @param string $right right name as used in myRights, e.g. mayRead
	 */
	public function may(string $right): bool {
		return (bool)($this->parameters['myRights'][$right] ?? false);
	}

}
