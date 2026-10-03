<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\DAV\Contacts;

use Sabre\VObject\Component\VCard;

/**
 * Contact (vCard) helpers
 */
final class ContactUtile {
	/** properties that carry an X-ID parameter identifying the entry */
	private const IDENTIFIED_PROPERTIES = ['NICKNAME', 'PRONOUNS', 'TEL', 'EMAIL', 'ADR', 'ORG', 'TITLE', 'ROLE', 'NOTE', 'KEY'];

	public static function normalizeProperties(VCard $vObject): void {
		foreach (self::IDENTIFIED_PROPERTIES as $name) {
			foreach ($vObject->select($name) as $property) {
				if (empty(($property->parameters()['X-ID'] ?? null)?->getValue())) {
					$property->offsetUnset('X-ID');
					$property->add('X-ID', uniqid());
				}
			}
		}
	}
}
