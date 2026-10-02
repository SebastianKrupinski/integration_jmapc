<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Objects\File;

use OCA\JMAPC\Objects\File\FileNodeObject;
use PHPUnit\Framework\TestCase;

class FileNodeObjectTest extends TestCase {
	public function testDirectory(): void {
		$node = (new FileNodeObject())->fromJmap([
			'id' => 'b',
			'parentId' => null,
			'nodeType' => 'directory',
			'blobId' => null,
			'name' => 'Test',
			'created' => '2026-07-09T22:55:06Z',
			'modified' => '2026-07-09T22:55:06Z',
			'myRights' => ['mayRead' => true, 'mayAddChildren' => true, 'mayDelete' => false],
		]);

		self::assertSame('b', $node->id());
		self::assertNull($node->in());
		self::assertSame('Test', $node->label());
		self::assertTrue($node->isDirectory());
		self::assertSame(strtotime('2026-07-09T22:55:06Z'), $node->modified());
		self::assertTrue($node->may('mayAddChildren'));
		self::assertFalse($node->may('mayDelete'));
		self::assertFalse($node->may('mayShare'));
	}

	public function testFile(): void {
		$node = (new FileNodeObject())->fromJmap([
			'id' => 'c',
			'parentId' => 'b',
			'nodeType' => 'file',
			'blobId' => 'B1',
			'name' => 'notes.txt',
			'size' => 42,
			'type' => 'text/plain',
			'created' => '2026-07-09T22:55:06Z',
		]);

		self::assertFalse($node->isDirectory());
		self::assertSame('b', $node->in());
		self::assertSame('B1', $node->blob());
		self::assertSame(42, $node->size());
		self::assertSame('text/plain', $node->type());
		self::assertSame(strtotime('2026-07-09T22:55:06Z'), $node->modified());
	}

	public function testNodeTypeFallsBackToBlob(): void {
		self::assertTrue((new FileNodeObject())->fromJmap(['id' => 'a'])->isDirectory());
		self::assertFalse((new FileNodeObject())->fromJmap(['id' => 'a', 'blobId' => 'B1'])->isDirectory());
		self::assertSame(0, (new FileNodeObject())->fromJmap(['id' => 'a'])->modified());
	}
}
