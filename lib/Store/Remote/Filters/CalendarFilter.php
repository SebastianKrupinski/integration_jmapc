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
namespace OCA\JMAPC\Store\Remote\Filters;

use OCA\JMAPC\Store\Common\Filters\FilterOperator;
use OCA\JMAPC\Store\Common\Filters\IFilter;

class CalendarFilter implements IFilter {

	private array $conditions = [];

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	public function comparators(): array {
		return [
			'before',
			'after',
			'uid',
			'text',
			'title',
			'description',
			'location',
			'owner',
			'attendee',
		];
	}

	/**
	 *
	 * @since 1.0.0
	 *
	 */
	public function condition(string $property, mixed $value, FilterOperator $operator = FilterOperator::AND): void {
		$this->conditions[$property] = [$operator, $property, $value];
	}

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function conditions(): array {
		return $this->conditions;
	}

}
