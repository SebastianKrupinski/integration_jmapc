<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Fixtures;

use OCA\JMAPC\Objects\Contact\ContactObject;
use OCA\JMAPC\Service\Local\LocalContactsService;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

/**
 * Contact test cases stored as vCards in tests/php/resources/contacts
 */
final class ContactFixtures extends ObjectFixtures {

	protected static function directory(): string {
		return __DIR__ . '/../../resources/contacts';
	}

	protected static function extension(): string {
		return 'vcf';
	}

	protected static function ignored(): array {
		return ['Origin', 'ID', 'CID', 'Signature', 'CCID', 'CEID', 'CESN', 'CreatedOn', 'ModifiedOn'];
	}

	protected static function normalize(array $values): array {
		$values['/Kind'] ??= 'individual';
		return $values;
	}

	protected static function exportDate(string $path, \DateTimeInterface $value): string {
		// anniversaries are dates, their time of day is not contact data
		if (str_starts_with($path, '/Anniversaries/')) {
			return $value->format('Y-m-d');
		}
		return parent::exportDate($path, $value);
	}

	public static function vcard(string $name): VCard {
		return Reader::read(self::source($name));
	}

	/**
	 * @param list<string> $warnings receives the warnings raised during conversion
	 */
	public static function contact(string $name, array &$warnings): ContactObject {
		return self::capture(static fn () => (new LocalContactsService())->toContactObject(self::vcard($name)), $warnings);
	}
}
