<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\JMAPC\Providers\Files\StorageCleaner;
use OCA\JMAPC\Service\FilesService;
use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Store\Local\FileCollectionEntity;
use OCA\JMAPC\Store\Local\FilesStore;
use OCA\JMAPC\Store\Local\Filters\FileCollectionFilter;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\InvalidCharacterInPathException;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class FilesServiceTest extends TestCase {
	private FilesStore&MockObject $store;
	private ServicesService&MockObject $servicesService;
	private IRootFolder&MockObject $rootFolder;
	private IFilenameValidator&MockObject $filenameValidator;
	private StorageCleaner&MockObject $storageCleaner;
	private IUserFolder&MockObject $userFolder;
	private FilesService&MockObject $service;

	protected function setUp(): void {
		parent::setUp();

		$this->store = $this->createMock(FilesStore::class);
		$this->store->method('collectionListFilter')->willReturnCallback(static fn () => new FileCollectionFilter());
		$this->servicesService = $this->createMock(ServicesService::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->filenameValidator = $this->createMock(IFilenameValidator::class);
		$this->storageCleaner = $this->createMock(StorageCleaner::class);

		$this->userFolder = $this->createMock(IUserFolder::class);
		$this->userFolder->method('getMountPoint')->willReturn($this->mountPoint('/user1/'));
		$this->rootFolder->method('getUserFolder')->with('user1')->willReturn($this->userFolder);

		$this->service = $this->getMockBuilder(FilesService::class)
			->setConstructorArgs([$this->store, $this->servicesService, $this->rootFolder, $this->filenameValidator, $this->storageCleaner])
			->onlyMethods(['remoteCapable'])
			->getMock();
		$this->service->method('remoteCapable')->willReturn(true);
	}

	private function mountPoint(string $path): IMountPoint&MockObject {
		$mountPoint = $this->createMock(IMountPoint::class);
		$mountPoint->method('getMountPoint')->willReturn($path);
		return $mountPoint;
	}

	private function remoteService(string $auth = 'BA'): ServiceEntity {
		$service = new ServiceEntity();
		$service->setId(2);
		$service->setUid('user1');
		$service->setLabel('Fastmail');
		$service->setAuth($auth);
		return $service;
	}

	private function mount(int $id, string $location, int $sid = 2): FileCollectionEntity {
		$mount = new FileCollectionEntity();
		$mount->setId($id);
		$mount->setUid('user1');
		$mount->setSid($sid);
		$mount->setMode('live');
		$mount->setLocation($location);
		$mount->resetUpdatedFields();
		return $mount;
	}

	public static function validLocations(): array {
		return [
			['JMAP', '/JMAP'],
			['/JMAP/Fastmail/', '/JMAP/Fastmail'],
			['//JMAP///Fastmail', '/JMAP/Fastmail'],
		];
	}

	#[DataProvider('validLocations')]
	public function testNormalizeLocation(string $location, string $expected): void {
		self::assertSame($expected, $this->service->normalizeLocation($location));
	}

	public static function rootLocations(): array {
		return [[''], ['/'], ['///']];
	}

	#[DataProvider('rootLocations')]
	public function testNormalizeLocationRejectsRoot(string $location): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('root folder');
		$this->service->normalizeLocation($location);
	}

	public function testNormalizeLocationRejectsInvalidSegment(): void {
		$this->filenameValidator->method('validateFilename')
			->willReturnCallback(static function (string $name): void {
				if ($name === '..') {
					throw new InvalidCharacterInPathException('dot');
				}
			});
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Mount location is invalid');
		$this->service->normalizeLocation('/JMAP/../Other');
	}

	public function testCreate(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->with('user1', 2)->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->userFolder->method('nodeExists')->with('/JMAP')->willReturn(false);
		$this->store->expects(self::once())->method('collectionCreate')->willReturnArgument(0);

		$mount = $this->service->create('user1', 2, 'JMAP/');

		self::assertSame('user1', $mount->getUid());
		self::assertSame(2, $mount->getSid());
		self::assertSame('live', $mount->getMode());
		self::assertSame('/JMAP', $mount->getLocation());
		self::assertSame('Fastmail', $mount->getLabel());
		self::assertTrue($mount->getVisible());
		self::assertArrayHasKey('visible', $mount->getUpdatedFields());
		self::assertNull($mount->getCcid());
		self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $mount->getUuid());
	}

	public function testCreateUsesGivenLabelInsideExistingFolder(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->userFolder->method('nodeExists')->willReturn(false);
		$parent = $this->createMock(Folder::class);
		$parent->method('getMountPoint')->willReturn($this->mountPoint('/user1/'));
		$this->userFolder->method('get')->with('/Remote')->willReturn($parent);
		$this->store->method('collectionCreate')->willReturnArgument(0);

		$mount = $this->service->create('user1', 2, '/Remote/JMAP', 'live', 'Mail');

		self::assertSame('/Remote/JMAP', $mount->getLocation());
		self::assertSame('Mail', $mount->getLabel());
	}

	public function testCreateRejectsForeignService(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn(null);
		$this->store->expects(self::never())->method('collectionCreate');
		$this->service->expects(self::never())->method('remoteCapable');

		$this->expectExceptionMessage('Service not found');
		$this->service->create('user1', 2, '/JMAP');
	}

	public function testCreateRejectsOAuthService(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService('OA'));
		$this->store->expects(self::never())->method('collectionCreate');

		$this->expectExceptionMessage('OAuth');
		$this->service->create('user1', 2, '/JMAP');
	}

	public function testCreateRejectsSecondMountForService(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([1 => $this->mount(1, '/Other')]);
		$this->store->expects(self::never())->method('collectionCreate');

		$this->expectExceptionMessage('already mounted');
		$this->service->create('user1', 2, '/JMAP');
	}

	public function testCreateRejectsUnsupportedMode(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->store->expects(self::never())->method('collectionCreate');

		$this->expectExceptionMessage('mode is not supported');
		$this->service->create('user1', 2, '/JMAP', 'cached');
	}

	public static function overlappingLocations(): array {
		return [
			'same' => ['/JMAP', '/JMAP'],
			'inside existing' => ['/JMAP', '/JMAP/Inner'],
			'around existing' => ['/JMAP/Inner', '/JMAP'],
		];
	}

	#[DataProvider('overlappingLocations')]
	public function testCreateRejectsOverlappingMount(string $existing, string $location): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturnCallback(fn (FileCollectionFilter $filter) => $filter->conditions()[0]['attribute'] === 'sid'
			? []
			: [7 => $this->mount(7, $existing, 3)]);
		$this->store->expects(self::never())->method('collectionCreate');

		$this->expectExceptionMessage('overlaps another mount');
		$this->service->create('user1', 2, $location);
	}

	public function testCreateAllowsSiblingWithSharedPrefix(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturnCallback(fn (FileCollectionFilter $filter) => $filter->conditions()[0]['attribute'] === 'sid'
			? []
			: [7 => $this->mount(7, '/JMAP', 3)]);
		$this->userFolder->method('nodeExists')->willReturn(false);
		$this->store->expects(self::once())->method('collectionCreate')->willReturnArgument(0);

		$this->service->create('user1', 2, '/JMAP2');
	}

	public function testCreateRejectsExistingNode(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->userFolder->method('nodeExists')->with('/JMAP')->willReturn(true);
		$this->store->expects(self::never())->method('collectionCreate');

		$this->expectExceptionMessage('already exists');
		$this->service->create('user1', 2, '/JMAP');
	}

	public function testCreateRejectsMissingParent(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->userFolder->method('nodeExists')->willReturn(false);
		$this->userFolder->method('get')->with('/Missing')->willThrowException(new NotFoundException());

		$this->expectExceptionMessage('parent folder does not exist');
		$this->service->create('user1', 2, '/Missing/JMAP');
	}

	public function testCreateRejectsFileParent(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->userFolder->method('nodeExists')->willReturn(false);
		$this->userFolder->method('get')->willReturn($this->createMock(File::class));

		$this->expectExceptionMessage('parent is not a folder');
		$this->service->create('user1', 2, '/file.txt/JMAP');
	}

	public function testCreateRejectsParentInOtherStorage(): void {
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->userFolder->method('nodeExists')->willReturn(false);
		$parent = $this->createMock(Folder::class);
		$parent->method('getMountPoint')->willReturn($this->mountPoint('/user1/files/Shared/'));
		$this->userFolder->method('get')->willReturn($parent);

		$this->expectExceptionMessage('inside another storage');
		$this->service->create('user1', 2, '/Shared/JMAP');
	}

	public function testCreateRejectsServiceWithoutFiles(): void {
		$service = $this->getMockBuilder(FilesService::class)
			->setConstructorArgs([$this->store, $this->servicesService, $this->rootFolder, $this->filenameValidator, $this->storageCleaner])
			->onlyMethods(['remoteCapable'])
			->getMock();
		$service->method('remoteCapable')->willReturn(false);
		$this->servicesService->method('fetchByUserIdAndServiceId')->willReturn($this->remoteService());
		$this->store->method('collectionList')->willReturn([]);
		$this->userFolder->method('nodeExists')->willReturn(false);
		$this->store->expects(self::never())->method('collectionCreate');

		$this->expectExceptionMessage('does not support files');
		$service->create('user1', 2, '/JMAP');
	}

	public function testFetchByUserIdAndCollectionIdHidesForeignCollection(): void {
		$mount = $this->mount(5, '/JMAP');
		$mount->setUid('user2');
		$this->store->method('collectionFetch')->with(5)->willReturn($mount);

		self::assertNull($this->service->fetchByUserIdAndCollectionId('user1', 5));
	}

	public function testModifyRejectsForeignMount(): void {
		$mount = $this->mount(5, '/JMAP');
		$mount->setUid('user2');
		$this->store->method('collectionFetch')->willReturn($mount);
		$this->store->expects(self::never())->method('collectionModify');

		$this->expectExceptionMessage('Mount not found');
		$this->service->modify('user1', 5, label: 'Mine');
	}

	public function testModifyKeepsUnchangedLocationWithoutChecks(): void {
		$this->store->method('collectionFetch')->willReturn($this->mount(5, '/JMAP'));
		$this->rootFolder->expects(self::never())->method('getUserFolder');
		$this->store->expects(self::once())->method('collectionModify')->willReturnArgument(0);

		$mount = $this->service->modify('user1', 5, '/JMAP/', null, 'Mail', false);

		self::assertSame('/JMAP', $mount->getLocation());
		self::assertSame('Mail', $mount->getLabel());
		self::assertFalse($mount->getVisible());
		self::assertArrayNotHasKey('location', $mount->getUpdatedFields());
	}

	public function testModifyMovesMountIgnoringItself(): void {
		$this->store->method('collectionFetch')->willReturn($this->mount(5, '/JMAP'));
		$this->store->method('collectionList')->willReturn([5 => $this->mount(5, '/JMAP')]);
		$this->userFolder->method('nodeExists')->with('/JMAP/Inner')->willReturn(false);
		$this->userFolder->method('get')->willThrowException(new NotFoundException());

		$this->expectExceptionMessage('parent folder does not exist');
		$this->service->modify('user1', 5, '/JMAP/Inner');
	}

	public function testModifyRelocates(): void {
		$this->store->method('collectionFetch')->willReturn($this->mount(5, '/JMAP'));
		$this->store->method('collectionList')->willReturn([5 => $this->mount(5, '/JMAP')]);
		$this->userFolder->method('nodeExists')->with('/Fastmail')->willReturn(false);
		$this->store->expects(self::once())->method('collectionModify')->willReturnArgument(0);

		self::assertSame('/Fastmail', $this->service->modify('user1', 5, 'Fastmail')->getLocation());
	}

	public function testModifyRejectsUnsupportedMode(): void {
		$this->store->method('collectionFetch')->willReturn($this->mount(5, '/JMAP'));
		$this->store->expects(self::never())->method('collectionModify');

		$this->expectExceptionMessage('mode is not supported');
		$this->service->modify('user1', 5, mode: 'other');
	}

	public function testFetchActiveByUserId(): void {
		$mounts = [5 => $this->mount(5, '/JMAP')];
		$this->store->expects(self::once())->method('collectionListActive')->with('user1', 'live')->willReturn($mounts);

		self::assertSame($mounts, $this->service->fetchActiveByUserId('user1'));
	}

	public function testDelete(): void {
		$mount = $this->mount(5, '/JMAP');
		$this->store->method('collectionFetch')->willReturn($mount);
		$this->store->expects(self::once())->method('collectionDelete')->with($mount);
		$this->storageCleaner->expects(self::once())->method('remove')->with('jmapc::files::5');

		$this->service->delete('user1', 5);
	}

	public function testDeleteRejectsForeignMount(): void {
		$mount = $this->mount(5, '/JMAP');
		$mount->setUid('user2');
		$this->store->method('collectionFetch')->willReturn($mount);
		$this->store->expects(self::never())->method('collectionDelete');
		$this->storageCleaner->expects(self::never())->method('remove');

		$this->expectExceptionMessage('Mount not found');
		$this->service->delete('user1', 5);
	}

	public function testDeleteByServiceRemovesStorages(): void {
		$this->store->method('collectionList')->willReturn([5 => $this->mount(5, '/JMAP'), 6 => $this->mount(6, '/Other')]);
		$this->store->expects(self::once())->method('collectionDeleteByService')->with(2)->willReturn(2);
		$removed = [];
		$this->storageCleaner->method('remove')->willReturnCallback(static function (string $id) use (&$removed): void {
			$removed[] = $id;
		});

		self::assertSame(2, $this->service->deleteByService(2));
		self::assertSame(['jmapc::files::5', 'jmapc::files::6'], $removed);
	}

	public function testDeleteByUserRemovesStorages(): void {
		$this->store->method('collectionList')->willReturn([5 => $this->mount(5, '/JMAP')]);
		$this->store->expects(self::once())->method('collectionDeleteByUser')->with('user1')->willReturn(1);
		$this->storageCleaner->expects(self::once())->method('remove')->with('jmapc::files::5');

		self::assertSame(1, $this->service->deleteByUser('user1'));
	}
}
