<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\JMAPC\Controller\UserConfigurationController;
use OCA\JMAPC\Service\ConfigurationService;
use OCA\JMAPC\Service\CoreService;
use OCA\JMAPC\Service\FilesService;
use OCA\JMAPC\Service\HarmonizationService;
use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Store\Local\FileCollectionEntity;
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;

class UserConfigurationControllerTest extends TestCase {
	private FilesService&MockObject $filesService;
	private LoggerInterface&MockObject $logger;
	private UserConfigurationController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->filesService = $this->createMock(FilesService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->controller = new UserConfigurationController(
			'integration_jmapc',
			$this->createMock(IRequest::class),
			$this->createMock(ConfigurationService::class),
			$this->createMock(CoreService::class),
			$this->createMock(HarmonizationService::class),
			$this->createMock(ServicesService::class),
			$this->filesService,
			$this->logger,
			'user1',
		);
	}

	public function testFilesCollectionList(): void {
		$mounts = [5 => new FileCollectionEntity()];
		$this->filesService->expects(self::once())->method('fetchByUserId')->with('user1')->willReturn($mounts);

		$response = $this->controller->filesCollectionList();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($mounts, $response->getData());
	}

	public function testFilesCollectionCreateUsesSessionUser(): void {
		$mount = new FileCollectionEntity();
		$this->filesService->expects(self::once())->method('create')
			->with('user1', 2, '/JMAP', 'live', 'Mail')
			->willReturn($mount);

		$response = $this->controller->filesCollectionCreate(2, '/JMAP', 'live', 'Mail');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($mount, $response->getData());
	}

	public function testFilesCollectionCreateValidationFailure(): void {
		$this->filesService->method('create')->willThrowException(new InvalidArgumentException('Mount location already exists'));
		$this->logger->expects(self::never())->method('error');

		$response = $this->controller->filesCollectionCreate(2, '/JMAP');

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame('Mount location already exists', $response->getData());
	}

	public function testFilesCollectionCreateUnexpectedFailureIsLoggedNotExposed(): void {
		$this->filesService->method('create')->willThrowException(new RuntimeException('SQLSTATE secret detail'));
		$this->logger->expects(self::once())->method('error');

		$response = $this->controller->filesCollectionCreate(2, '/JMAP');

		self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		self::assertSame('Files operation failed', $response->getData());
	}

	public function testFilesCollectionModifyPassesOnlyGivenValues(): void {
		$mount = new FileCollectionEntity();
		$this->filesService->expects(self::once())->method('modify')
			->with('user1', 5, null, null, 'Mail', false)
			->willReturn($mount);

		$response = $this->controller->filesCollectionModify(5, label: 'Mail', visible: false);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testFilesCollectionDelete(): void {
		$this->filesService->expects(self::once())->method('delete')->with('user1', 5);

		$response = $this->controller->filesCollectionDelete(5);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testFilesCollectionDeleteMissing(): void {
		$this->filesService->method('delete')->willThrowException(new InvalidArgumentException('Mount not found'));

		$response = $this->controller->filesCollectionDelete(5);

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}
}
