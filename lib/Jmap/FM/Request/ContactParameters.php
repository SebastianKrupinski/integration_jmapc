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

use JmapClient\Requests\RequestParameters;

use function PHPUnit\Framework\isEmpty;

class ContactParameters extends RequestParameters {
	
	public const DATE_FORMAT_ANNIVERSARY = 'YYYY-MM-DD';

	public function __construct(&$parameters = null) {
		parent::__construct($parameters);
	}

	public function in(string $value): self {
		if (isEmpty($value)) {
			$this->parameter('addressbookId', 'Default');
		} else {
			$this->parameter('addressbookId', $value);
		}
		return $this;
	}

	public function id(string $value): self {
		$this->parameter('id', $value);
		return $this;
	}

	public function uid(string $value): self {
		$this->parameter('uid', $value);
		return $this;
	}

	public function type(string $value): self {
		$this->parameter('kind', $value);
		return $this;
	}

	public function nameLast(string $value): self {
		$this->parameter('lastName', $value);
		return $this;
	}

	public function nameFirst(string $value): self {
		$this->parameter('firstName', $value);
		return $this;
	}

	public function namePrefix(string $value): self {
		$this->parameter('prefix', $value);
		return $this;
	}

	public function nameSuffix(string $value): self {
		$this->parameter('suffix', $value);
		return $this;
	}

	public function organizationName(string $value): self {
		$this->parameter('company', $value);
		return $this;
	}

	public function organizationUnit(string $value): self {
		$this->parameter('department', $value);
		return $this;
	}

	public function title(string $value): self {
		$this->parameter('jobTitle', $value);
		return $this;
	}

	public function notes(string $value): self {
		$this->parameter('notes', $value);
		return $this;
	}

	public function priority(int $value): self {
		$this->parameter('importance', $value);
		return $this;
	}

	public function birthDay(string $value): self {
		$this->parameter('birthday', $value);
		return $this;
	}

	public function nuptialDay(string $value): self {
		$this->parameter('anniversary', $value);
		return $this;
	}

	public function email(?int $id = null): ContactEmailParameters {
		// Ensure the parameter exists
		if (!isset($this->_parameters->emails)) {
			$this->_parameters->emails = [];
		}
		// If an ID is provided, ensure the specific email entry exists
		if ($id !== null) {
			if (!isset($this->_parameters->emails[$id])) {
				$this->_parameters->emails[$id] = new \stdClass();
			}
			return new ContactEmailParameters($this->_parameters->emails[$id]);
		}
		// If no ID is provided, create a new email entry
		$this->_parameters->emails[] = new \stdClass();
		return new ContactEmailParameters(end($this->_parameters->emails));
	}

	public function phone(?int $id = null): ContactPhoneParameters {
		// Ensure the parameter exists
		if (!isset($this->_parameters->phones)) {
			$this->_parameters->phones = [];
		}
		// If an ID is provided, ensure the specific phone entry exists
		if ($id !== null) {
			if (!isset($this->_parameters->phones[$id])) {
				$this->_parameters->phones[$id] = new \stdClass();
			}
			return new ContactPhoneParameters($this->_parameters->phones[$id]);
		}
		// If no ID is provided, create a new phone entry
		$this->_parameters->phones[] = new \stdClass();
		return new ContactPhoneParameters(end($this->_parameters->phones));
	}

	public function location(?int $id = null): ContactLocationParameters {
		// Ensure the parameter exists
		if (!isset($this->_parameters->addresses)) {
			$this->_parameters->addresses = [];
		}
		// If an ID is provided, ensure the specific address entry exists
		if ($id !== null) {
			if (!isset($this->_parameters->addresses[$id])) {
				$this->_parameters->addresses[$id] = new \stdClass();
			}
			return new ContactLocationParameters($this->_parameters->addresses[$id]);
		}
		// If no ID is provided, create a new address entry
		$this->_parameters->addresses[] = new \stdClass();
		return new ContactLocationParameters(end($this->_parameters->addresses));
	}

	public function online(?int $id = null): ContactOnlineParameters {
		// Ensure the parameter exists
		if (!isset($this->_parameters->addresses)) {
			$this->_parameters->addresses = [];
		}
		// If an ID is provided, ensure the specific address entry exists
		if ($id !== null) {
			if (!isset($this->_parameters->addresses[$id])) {
				$this->_parameters->addresses[$id] = new \stdClass();
			}
			return new ContactOnlineParameters($this->_parameters->addresses[$id]);
		}
		// If no ID is provided, create a new address entry
		$this->_parameters->addresses[] = new \stdClass();
		return new ContactOnlineParameters(end($this->_parameters->addresses));
	}

}
