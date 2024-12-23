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

namespace OCA\JMAPC\Objects;

class BaseCollection extends \ArrayObject
{
    private $type;

    public function __construct($type, $data = []) {
        $this->type = $type;
        parent::__construct($data);
    }

    private function validate($value): bool {
        return match ($this->type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            default => $value instanceof $this->type
        };
    }

    public function append($value): void {
        if (!$this->validate($value)) {
            throw new \InvalidArgumentException('Type error');
        }
        parent::append($value);
    }

    public function offsetSet($key, $value): void {
        if (!$this->validate($value)) {
            throw new \InvalidArgumentException('Type error');
        }
        parent::offsetSet($key, $value);
    }
}