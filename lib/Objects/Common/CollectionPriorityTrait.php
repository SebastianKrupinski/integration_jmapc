<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Common;

trait CollectionPriorityTrait {

	public function highestPriority(): int|string {
		$lowestNumber = null;
		$lowestIndex = null;
		foreach ($this->getIterator() as $index => $entry) {
			if ($entry->Priority !== null && ($lowestNumber === null || $entry->Priority < $lowestNumber)) {
				$lowestNumber = $entry->Priority;
				$lowestIndex = $index;
			}
		}
		if ($lowestIndex === null) {
			$this->getIterator()->rewind();
			$lowestIndex = $this->getIterator()->key();
		}
		return $lowestIndex;
	}

	public function lowestPriority(): int|string {
		$highestNumber = null;
		$highestIndex = null;
		foreach ($this->getIterator() as $index => $entry) {
			if ($entry->Priority !== null && ($highestNumber === null || $entry->Priority > $highestNumber)) {
				$highestNumber = $entry->Priority;
				$highestIndex = $index;
			}
		}
		if ($highestIndex === null) {
			$highestIndex = $this->getIterator()->key();
		}
		return $highestIndex;
	}

}
