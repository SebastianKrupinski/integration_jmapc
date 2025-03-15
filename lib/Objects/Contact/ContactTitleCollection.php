<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
