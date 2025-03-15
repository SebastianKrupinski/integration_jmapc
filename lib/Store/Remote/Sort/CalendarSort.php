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
namespace OCA\JMAPC\Store\Remote\Sort;

use OCA\JMAPC\Store\Common\Sort\ISort;

class CalendarSort implements ISort {

	private array $conditions = [];

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	public function comparators(): array {
		return [
			'created',
			'modified',
			'start',
			'uid',
			'recurrence',
		];
	}

	/**
	 *
	 * @since 1.0.0
	 *
	 */
	public function condition(string $property, bool $direction): void {
		$this->conditions[$property] = ['property' => $property, 'direction' => $direction];
	}

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array{property: string, direction: bool}>
	 */
	public function conditions(): array {
		return $this->conditions;
	}

}
