<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

use DateInterval;
use DateTime;
use DateTimeImmutable;

class EventNotificationObject {
	public ?string $Id = null;
	public ?EventNotificationTypes $Type = null;
	public ?EventNotificationPatterns $Pattern = null;
	public DateTime|DateTimeImmutable|null $When = null;
	public ?EventNotificationAnchorTypes $Anchor = null;
	public ?DateInterval $Offset = null;
}
