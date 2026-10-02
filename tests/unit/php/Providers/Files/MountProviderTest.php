<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Providers\Files;

use InvalidArgumentException;
use OC\Files\Cache\Watcher;
use OCA\JMAPC\Providers\Files\Live\MountPoint;
use OCA\JMAPC\Providers\Files\MountProvider;
use OCA\JMAPC\Service\FilesService;
use OCA\JMAPC\Store\Local\FileCollectionEntity;
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

class MountProviderTest extends TestCase {
	private FilesService&MockObject $filesService;

	protected function setUp(): void {
		parent::setUp();
		$this->filesService = $this->createMock(FilesService::class);
	}

	private function collection(int $id, string $location): FileCollectionEntity {
		$mount = new FileCollectionEntity();
		$mount->setId($id);
		$mount->setUid('user1');
		$mount->setSid(2);
		$mount->setMode('live');
		$mount->setLocation($location);
		$mount->resetUpdatedFields();
		return $mount;
	}

	public function testMountsForUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->filesService->expects(self::once())->method('fetchActiveByUserId')->with('user1')
			->willReturn([65 => $this->collection(65, '/jmap'), 66 => $this->collection(66, '/Remote/Mail')]);

		$mounts = (new MountProvider($this->filesService))->getMountsForUser($user, $this->createMock(IStorageFactory::class));

		self::assertCount(2, $mounts);
		self::assertSame('/user1/files/jmap/', $mounts[0]->getMountPoint());
		self::assertSame('/user1/files/Remote/Mail/', $mounts[1]->getMountPoint());
		self::assertSame(MountProvider::class, $mounts[0]->getMountProvider());
		self::assertSame(65, $mounts[0]->getCollection()->getId());
	}

	public function testMountOptions(): void {
		$mountPoint = new MountPoint($this->filesService, $this->collection(65, '/jmap'));

		self::assertFalse($mountPoint->getOption('encrypt', true));
		self::assertFalse($mountPoint->getOption('enable_sharing', true));
		self::assertTrue($mountPoint->getOption('readonly', false));
		self::assertSame(Watcher::CHECK_ONCE, $mountPoint->getOption('filesystem_check_changes', null));
	}

	public function testMoveMountUpdatesLocation(): void {
		$moved = $this->collection(65, '/Archive/jmap');
		$this->filesService->expects(self::once())->method('modify')->with('user1', 65, '/Archive/jmap')->willReturn($moved);
		$mountPoint = new MountPoint($this->filesService, $this->collection(65, '/jmap'));

		self::assertTrue($mountPoint->moveMount('/user1/files/Archive/jmap'));
		self::assertSame('/user1/files/Archive/jmap/', $mountPoint->getMountPoint());
		self::assertSame($moved, $mountPoint->getCollection());
	}

	public function testMoveMountRejected(): void {
		$this->filesService->method('modify')->willThrowException(new InvalidArgumentException('Mount location can not be inside another storage'));
		$mountPoint = new MountPoint($this->filesService, $this->collection(65, '/jmap'));

		self::assertFalse($mountPoint->moveMount('/user1/files/Shared/jmap'));
		self::assertSame('/user1/files/jmap/', $mountPoint->getMountPoint());
	}

	public function testRemoveMountDeletesConfigurationOnly(): void {
		$this->filesService->expects(self::once())->method('delete')->with('user1', 65);
		$mountPoint = new MountPoint($this->filesService, $this->collection(65, '/jmap'));

		self::assertTrue($mountPoint->removeMount());
	}

	public function testRemoveMountMissing(): void {
		$this->filesService->method('delete')->willThrowException(new InvalidArgumentException('Mount not found'));
		$mountPoint = new MountPoint($this->filesService, $this->collection(65, '/jmap'));

		self::assertFalse($mountPoint->removeMount());
	}
}
