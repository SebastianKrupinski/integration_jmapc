<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Task;

use DateTime;

class TaskOccurrenceObject {
	public ?string $Pattern = null;         // Pattern - A - Absolute / R - Relative
	public ?string $Precision = null;       // Time Scale - D - Daily / W - Weekly / M - Monthly / Y - Yearly
	public ?string $Interval = null;        // Time Interval - Every 2 Days / Every 4 Weeks / Every 1 Year
	public ?string $Iterations = null;      // Number of recurrence
	public ?DateTime $Concludes = null;     // Date to stop recurrence
	public array $Excludes = [];
	public array $OnDayOfWeek = [];
	public array $OnDayOfMonth = [];
	public array $OnDayOfYear = [];
	public array $OnWeekOfMonth = [];
	public array $OnWeekOfYear = [];
	public array $OnMonthOfYear = [];

	public function __construct() {
	}
}
