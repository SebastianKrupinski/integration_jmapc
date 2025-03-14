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
