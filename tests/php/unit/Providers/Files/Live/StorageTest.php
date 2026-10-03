<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Providers\Files\Live;

use Exception;
use OCA\JMAPC\Objects\File\FileNodeObject;
use OCA\JMAPC\Providers\Files\Live\Storage;
use OCA\JMAPC\Service\Remote\RemoteFilesService;
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\StorageNotAvailableException;
use PHPUnit\Framework\MockObject\MockObject;

class StorageTest extends TestCase {
	private const ALL_RIGHTS = ['mayRead' => true, 'mayAddChildren' => true, 'mayRename' => true, 'mayDelete' => true, 'mayModifyContent' => true, 'mayShare' => true];

	private RemoteFilesService&MockObject $remote;
	/** @var array<string, list<FileNodeObject>> children indexed by parent id, '' for the top level */
	private array $tree = [];
	/** @var list<?string> parent ids of every listing request */
	private array $listed = [];
	private Storage $storage;

	protected function setUp(): void {
		parent::setUp();
		$this->remote = $this->createMock(RemoteFilesService::class);
		$this->remote->method('nodeList')->willReturnCallback(function (?string $location): array {
			$this->listed[] = $location;
			return ['state' => 'S1', 'list' => $this->tree[$location ?? ''] ?? []];
		});
		$this->storage = new Storage(['cid' => 65, 'sid' => 2, 'remote' => $this->remote]);

		$this->tree = [
			'' => [
				$this->node('d1', null, 'Documents', true, modified: '2026-07-01T00:00:00Z'),
				$this->node('f1', null, 'readme.md', false, 'B1', 12, 'text/markdown', '2026-07-03T00:00:00Z'),
			],
			'd1' => [
				$this->node('d2', 'd1', 'Archive', true),
				$this->node('f2', 'd1', 'photo.jpg', false, 'B2', 2048, null, '2026-07-02T00:00:00Z', ['mayRead' => true]),
			],
			'd2' => [],
		];
	}

	private function node(string $id, ?string $parent, string $name, bool $directory, ?string $blob = null, int $size = 0, ?string $type = null, string $modified = '2026-06-01T00:00:00Z', array $rights = self::ALL_RIGHTS): FileNodeObject {
		return (new FileNodeObject())->fromJmap([
			'id' => $id,
			'parentId' => $parent,
			'name' => $name,
			'nodeType' => $directory ? 'directory' : 'file',
			'blobId' => $blob,
			'size' => $directory ? null : $size,
			'type' => $type,
			'modified' => $modified,
			'myRights' => $rights,
		]);
	}

	public function testStorageId(): void {
		self::assertSame('jmapc::files::65', $this->storage->getId());
		self::assertSame('jmapc::files::65', Storage::storageId(65));
	}

	public function testRootMetaData(): void {
		$meta = $this->storage->getMetaData('');

		self::assertSame('httpd/unix-directory', $meta['mimetype']);
		self::assertSame(strtotime('2026-07-03T00:00:00Z'), $meta['mtime']);
		self::assertSame(-1, $meta['size']);
		self::assertSame(md5('root|S1'), $meta['etag']);
		self::assertTrue($this->storage->file_exists(''));
		self::assertTrue($this->storage->is_dir('/'));
	}

	public function testDirectoryContentOfRoot(): void {
		$entries = iterator_to_array($this->storage->getDirectoryContent(''), false);

		self::assertSame(['Documents', 'readme.md'], array_column($entries, 'name'));
		self::assertSame(['httpd/unix-directory', 'text/markdown'], array_column($entries, 'mimetype'));
		self::assertSame([-1, 12], array_column($entries, 'size'));
		self::assertSame([null], $this->listed);
	}

	public function testNestedPathResolvesOnceListingPerFolder(): void {
		$meta = $this->storage->getMetaData('Documents/photo.jpg');
		$this->storage->getMetaData('/Documents/Archive/');
		$this->storage->file_exists('Documents/photo.jpg');

		self::assertSame('photo.jpg', $meta['name']);
		self::assertSame(2048, $meta['size']);
		self::assertSame('image/jpeg', $meta['mimetype']);
		self::assertSame([null, 'd1'], $this->listed);
	}

	public function testMissingPaths(): void {
		self::assertNull($this->storage->getMetaData('Missing'));
		self::assertNull($this->storage->getMetaData('readme.md/child'));
		self::assertFalse($this->storage->file_exists('Documents/missing.txt'));
		self::assertFalse($this->storage->stat('Missing'));
		self::assertFalse($this->storage->filetype('Missing'));
		self::assertFalse($this->storage->opendir('readme.md'));
		self::assertSame([], iterator_to_array($this->storage->getDirectoryContent('Missing')));
	}

	public function testFiletypeAndOpendir(): void {
		self::assertSame('dir', $this->storage->filetype('Documents'));
		self::assertSame('file', $this->storage->filetype('readme.md'));

		$handle = $this->storage->opendir('Documents');
		$names = [];
		while (($name = readdir($handle)) !== false) {
			$names[] = $name;
		}
		self::assertSame(['Archive', 'photo.jpg'], $names);
	}

	public function testPermissionsFollowRights(): void {
		$all = Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE | Constants::PERMISSION_DELETE;
		self::assertSame($all, $this->storage->getPermissions('Documents'));
		self::assertSame($all & ~Constants::PERMISSION_CREATE, $this->storage->getPermissions('readme.md'));
		self::assertSame(Constants::PERMISSION_READ, $this->storage->getPermissions('Documents/photo.jpg'));
		self::assertTrue($this->storage->isReadable('Documents/photo.jpg'));
		self::assertFalse($this->storage->isUpdatable('Documents/photo.jpg'));
		self::assertFalse($this->storage->isDeletable('Documents/photo.jpg'));
		self::assertFalse($this->storage->isSharable('Documents'));
		self::assertSame(0, $this->storage->getPermissions('Missing'));
	}

	public function testEtags(): void {
		self::assertSame(md5('f1|B1|' . strtotime('2026-07-03T00:00:00Z')), $this->storage->getETag('readme.md'));
		self::assertSame(md5('d1|' . strtotime('2026-07-01T00:00:00Z') . '|S1'), $this->storage->getETag('Documents'));
		self::assertFalse($this->storage->getETag('Missing'));
	}

	public function testHasUpdated(): void {
		$mtime = strtotime('2026-07-03T00:00:00Z');
		self::assertFalse($this->storage->hasUpdated('readme.md', $mtime));
		self::assertTrue($this->storage->hasUpdated('readme.md', $mtime - 1));
		self::assertTrue($this->storage->hasUpdated('Documents', PHP_INT_MAX));
		self::assertTrue($this->storage->hasUpdated('', PHP_INT_MAX));
		self::assertTrue($this->storage->hasUpdated('Missing', 0));
	}

	public function testFopenReadsContents(): void {
		$stream = fopen('php://memory', 'r+');
		$this->remote->expects(self::once())->method('nodeContents')
			->with(self::callback(static fn (FileNodeObject $node): bool => $node->id() === 'f1'))
			->willReturn($stream);

		self::assertSame($stream, $this->storage->fopen('readme.md', 'r'));
	}

	public function testFopenRejectsFoldersMissingAndWrites(): void {
		$this->remote->expects(self::never())->method('nodeContents');

		self::assertFalse($this->storage->fopen('Documents', 'r'));
		self::assertFalse($this->storage->fopen('missing.txt', 'r'));
		self::assertFalse($this->storage->fopen('readme.md', 'w'));
	}

	public function testWritesAreRejected(): void {
		self::assertFalse($this->storage->mkdir('New'));
		self::assertFalse($this->storage->rmdir('Documents'));
		self::assertFalse($this->storage->unlink('readme.md'));
		self::assertFalse($this->storage->touch('readme.md'));
		self::assertFalse($this->storage->rename('readme.md', 'other.md'));
		self::assertFalse($this->storage->copy('readme.md', 'other.md'));
	}

	public function testFreeSpaceUnknown(): void {
		self::assertSame(FileInfo::SPACE_UNKNOWN, $this->storage->free_space(''));
	}

	public function testUnavailableRemoteIsReportedNotEmpty(): void {
		$remote = $this->createMock(RemoteFilesService::class);
		$remote->method('nodeList')->willThrowException(new Exception('connection refused'));
		$storage = new Storage(['cid' => 65, 'sid' => 2, 'remote' => $remote]);

		$this->expectException(StorageNotAvailableException::class);
		iterator_to_array($storage->getDirectoryContent(''));
	}

	public function testUnavailableDownloadIsReported(): void {
		$this->remote->method('nodeContents')->willThrowException(new Exception('timeout'));

		$this->expectException(StorageNotAvailableException::class);
		$this->storage->fopen('readme.md', 'r');
	}
}
