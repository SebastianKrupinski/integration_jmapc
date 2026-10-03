<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

use OCA\JMAPC\Objects\BaseCollection;

class ContactTitleCollection extends BaseCollection {
	public function __construct($data = []) {
		parent::__construct(ContactTitleObject::class, $data);
	}

	public function highestPriority(ContactTitleTypes $type): int|string|null {
		$lowestNumber = null;
		$lowestIndex = null;
		$firstIndex = null;
		foreach ($this->getIterator() as $index => $entry) {
			if ($firstIndex === null && $entry->Kind === $type) {
				$firstIndex = $index;
			}
			if ($entry->Kind !== $type && $entry->Priority !== null && ($lowestNumber === null || $entry->Priority < $lowestNumber)) {
				$lowestNumber = $entry->Priority;
				$lowestIndex = $index;
			}
		}
		if ($lowestIndex === null) {
			return $firstIndex;
		} else {
			return $lowestIndex;
		}
	}

	public function lowestPriority(ContactTitleTypes $type): int|string|null {
		$highestNumber = null;
		$highestIndex = null;
		$lastIndex = null;
		foreach ($this->getIterator() as $index => $entry) {
			if ($entry->Kind !== $type && $entry->Priority !== null && ($highestNumber === null || $entry->Priority > $highestNumber)) {
				$highestNumber = $entry->Priority;
				$highestIndex = $index;
			}
			if ($entry->Kind === $type) {
				$lastIndex = $index;
			}
		}
		if ($highestIndex === null) {
			return $lastIndex;
		} else {
			return $highestIndex;
		}
	}
}
