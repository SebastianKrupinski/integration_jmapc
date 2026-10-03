<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Mail;

class Status {
	public function __construct(
		public readonly string $conclusion,
	) {

		$this->conclusion = $conclusion;

	}

}
