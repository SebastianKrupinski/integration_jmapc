<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Common;

trait CollectionIndexTrait {

	public function highestIndex(): int|string {
		$highestNumber = null;
		$highestIndex = null;
		foreach ($this->getIterator() as $index => $entry) {
			if ($entry->Index !== null && ($highestNumber === null || $entry->Index > $highestNumber)) {
				$highestNumber = $entry->Index;
				$highestIndex = $index;
			}
		}
		if ($highestIndex === null) {
			$highestIndex = $this->getIterator()->key();
		}
		return $highestIndex;
	}

	public function lowestIndex(): int|string {
		$lowestNumber = null;
		$lowestIndex = null;
		foreach ($this->getIterator() as $index => $entry) {
			if ($entry->Index !== null && ($lowestNumber === null || $entry->Index < $lowestNumber)) {
				$lowestNumber = $entry->Index;
				$lowestIndex = $index;
			}
		}
		if ($lowestIndex === null) {
			$this->getIterator()->rewind();
			$lowestIndex = $this->getIterator()->key();
		}
		return $lowestIndex;
	}

}
