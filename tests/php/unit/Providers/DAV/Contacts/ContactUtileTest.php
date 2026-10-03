<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Providers\DAV\Contacts;

use OCA\JMAPC\Providers\DAV\Contacts\ContactUtile;
use OCA\JMAPC\Tests\Unit\TestCase;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

class ContactUtileTest extends TestCase {

	private function read(string ...$properties): VCard {
		return Reader::read(implode("\r\n", ['BEGIN:VCARD', 'VERSION:4.0', 'UID:test', 'FN:Test', ...$properties, 'END:VCARD']));
	}

	private function xid(VCard $vCard, string $name, int $index = 0): ?string {
		return ($vCard->select($name)[$index]->parameters()['X-ID'] ?? null)?->getValue();
	}

	public function testNormalizePropertiesAssignsMissingIds(): void {
		$vCard = $this->read(
			'EMAIL:one@example.com',
			'EMAIL:two@example.com',
			'TEL;X-ID=tel-1:+1-555-0100',
			'TITLE:Engineer',
			'ROLE:Lead',
		);

		ContactUtile::normalizeProperties($vCard);

		$this->assertNotEmpty($this->xid($vCard, 'EMAIL', 0));
		$this->assertNotEmpty($this->xid($vCard, 'EMAIL', 1));
		$this->assertNotSame($this->xid($vCard, 'EMAIL', 0), $this->xid($vCard, 'EMAIL', 1));
		$this->assertSame('tel-1', $this->xid($vCard, 'TEL'));
		$this->assertNotEmpty($this->xid($vCard, 'TITLE'));
		$this->assertNotEmpty($this->xid($vCard, 'ROLE'));
		$this->assertNull($this->xid($vCard, 'FN'));
	}

	public function testNormalizePropertiesReplacesEmptyId(): void {
		$vCard = $this->read('EMAIL;X-ID=:one@example.com');

		ContactUtile::normalizeProperties($vCard);

		$this->assertNotEmpty($this->xid($vCard, 'EMAIL'));
		$this->assertCount(1, $vCard->EMAIL->parameters()['X-ID']->getParts());
	}
}
