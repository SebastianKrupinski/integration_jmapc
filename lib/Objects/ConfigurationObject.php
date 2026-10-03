<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects;

use DateTimeZone;

class ConfigurationObject {

	public ?DateTimeZone $SystemTimeZone = null;
	public string $UserId = '';						// nextcloud user id
	public ?DateTimeZone $UserTimeZone = null; 		// nextcloud user timezone
	public int $ContactsHarmonize = -1;				// contacts harmonize
	public string $ContactsPrevalence = '';			// contacts prevalence
	public string $ContactsPresentation = '';
	public int $EventsHarmonize = -1;				// events harmonize
	public string $EventsPrevalence = '';			// events prevalence
	public int $TasksHarmonize = -1;				// tasks harmonize
	public string $TasksPrevalence = '';			// tasks prevalence
	public ?DateTimeZone $EventsTimezone = null;
	public string $AccountProvider = '';
	public string $AccountId = '';
	public string $AccountProtocol = '';
	public string $AccountConnected = '';

}
