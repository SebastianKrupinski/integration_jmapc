<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Jmap;

use Attribute;

/**
 * Skips the test when the Stalwart server is older than the given version
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class RequiresStalwart {
	public function __construct(
		public readonly string $version,
		public readonly string $reason,
	) {
	}
}
