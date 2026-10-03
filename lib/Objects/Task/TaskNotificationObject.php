<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Task;

class TaskNotificationObject {
	public ?string $Type = null;
	public ?string $Pattern = null;
	public $When = null;

	public function __construct(
		?string $Type = null,
		?string $Pattern = null,
		mixed $When = null,
	) {
		$this->Type = $Type;
		$this->Pattern = $Pattern;
		$this->When = $When;
	}
}
