<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

use DateTime;
use DateTimeImmutable;

class EventOccurrenceObject {
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

	public function __construct() {
	}
}
