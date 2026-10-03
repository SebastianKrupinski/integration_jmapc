<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

use OCA\JMAPC\Objects\OriginTypes;

class EventObject extends EventBaseObject {

	public ?OriginTypes $Origin = null;		// System
	public ?string $ID = null;              // System Entity Id
	public ?string $CID = null;             // System Collection Id
	public ?string $Signature = null;       // System Entity Signature
	public ?string $CCID = null;            // Correlation Collection Id
	public ?string $CEID = null;            // Correlation Entity Id
	public ?string $CESN = null;            // Correlation Signature

}
