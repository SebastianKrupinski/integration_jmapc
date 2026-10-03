<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service;

use JmapClient\Responses\Contacts\ContactParameters as ContactParametersResponse;
use OCA\JMAPC\Service\Local\LocalContactsService;
use OCA\JMAPC\Service\Remote\RemoteContactsService;
use OCA\JMAPC\Tests\Unit\Fixtures\ContactFixtures;
use OCA\JMAPC\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\VObject\Reader;

class ContactConversionTest extends TestCase {

	public static function fixtures(): array {
		return ContactFixtures::provider();
	}

	/**
	 * vCard -> contact -> vCard text -> contact
	 */
	#[DataProvider('fixtures')]
	public function testVCardRoundTrip(string $name): void {
		$warnings = [];
		$expected = ContactFixtures::contact($name, $warnings);

		$service = new LocalContactsService();
		$actual = ContactFixtures::capture(
			static fn () => $service->toContactObject(Reader::read($service->fromContactObject($expected)->serialize())),
			$warnings,
		);

		ContactFixtures::assertEquivalent($name, 'vcard', $expected, $actual, $warnings);
	}

	/**
	 * contact -> JSContact request -> JSON -> JSContact response -> contact
	 */
	#[DataProvider('fixtures')]
	public function testJmapRoundTrip(string $name): void {
		$warnings = [];
		$expected = ContactFixtures::contact($name, $warnings);

		$service = new RemoteContactsService();
		$actual = ContactFixtures::capture(static function () use ($service, $expected) {
			$card = null;
			$service->fromContactObject($expected)->bind($card);
			$wire = json_decode(json_encode($card, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
			return $service->toContactObject(new ContactParametersResponse($wire));
		}, $warnings);

		ContactFixtures::assertEquivalent($name, 'jmap', $expected, $actual, $warnings);
	}
}
