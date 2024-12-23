<?php
//declare(strict_types=1);

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

namespace OCA\JMAPC\Objects\Event;

use DateTime;
use DateTimeImmutable;

class EventOccurrenceObject {
    public ?EventOccurrencePatternTypes $Pattern = null;        // Pattern - Absolute / Relative
	public ?EventOccurrencePrecisionTypes $Precision = null;    // Time Interval
    public ?int $Interval = null;           // Time Interval - Every 2 Days / Every 4 Weeks / Every 1 Year
    public ?int $Iterations = null;         // Number of recurrence
    public DateTime|DateTimeImmutable|null $Concludes = null;     // Date to stop recurrence
    public ?String $Scale = null;           // calendar system in which this recurrence rule operates
    public array $OnDayOfWeek = [];
    public array $OnDayOfMonth = [];
    public array $OnDayOfYear = [];
    public array $OnWeekOfMonth = [];
    public array $OnWeekOfYear = [];
    public array $OnMonthOfYear = [];
    public array $OnHour = [];
    public array $OnMinute = [];
    public array $OnSecond = [];
    public array $OnPosition = [];

    public function __construct() {}
}
