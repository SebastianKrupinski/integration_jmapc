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
namespace OCA\JMAPC\Jmap\FM\Request;

use JmapClient\Requests\RequestSet;

class ContactSet extends RequestSet {

	public function __construct(string $account, string $identifier = '', ?string $namespace = null, ?string $resource = null) {

		$space = $namespace ?? 'urn:ietf:params:jmap:contacts';
		$class = $resource ?? 'Contact';

		parent::__construct($space, $class, $account, $identifier);
		
	}
	
	public function create(string $id, $object = null): ContactParameters {
		// evaluate if create parameter exist and create if needed
		if (!isset($this->_command['create'][$id]) && $object === null) {
			$this->_command['create'][$id] = new \stdClass();
		} elseif ($object !== null) {
			$object->bind($this->_command['create'][$id]);
		}
		// return self for function chaining
		return new ContactParameters($this->_command['create'][$id]);
	}

	public function update(string $id, $object = null): ContactParameters {
		// evaluate if create parameter exist and create if needed
		if (!isset($this->_command['update'][$id]) && $object === null) {
			$this->_command['update'][$id] = new \stdClass();
		} elseif ($object !== null) {
			$object->bind($this->_command['update'][$id]);
		}
		// return self for function chaining
		return new ContactParameters($this->_command['update'][$id]);
	}

}
