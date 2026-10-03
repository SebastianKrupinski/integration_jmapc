<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Event;

class EventBaseObject extends EventCommonObject {

	public ?string $UUID = null;
	public ?EventOccurrenceObject $OccurrencePattern = null;
	public EventOccurrenceCollection $OccurrenceExceptions;
	public EventMutationCollection $OccurrenceMutations;

	public function __construct() {
		parent::__construct();
		$this->OccurrenceExceptions = new EventOccurrenceCollection();
		$this->OccurrenceMutations = new EventMutationCollection();
	}

}
