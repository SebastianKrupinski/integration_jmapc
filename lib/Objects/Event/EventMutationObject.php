<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

use DateTime;
use DateTimeImmutable;

class EventMutationObject extends EventCommonObject {

	public DateTime|DateTimeImmutable|null $mutationId = null;
	public ?string $mutationTz = null;
	public ?bool $mutationExclusion = null;

}
