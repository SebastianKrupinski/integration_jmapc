<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Events;

use OCA\JMAPC\Events\UserDeletedListener;
use OCA\JMAPC\Service\CoreService;
use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

class UserDeletedListenerTest extends TestCase {
	private function service(int $id): ServiceEntity {
		$service = new ServiceEntity();
		$service->setId($id);
		$service->setUid('user1');
		return $service;
	}

	public function testDisconnectsEveryServiceOfUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$servicesService = $this->createMock(ServicesService::class);
		$servicesService->method('fetchByUserId')->with('user1')->willReturn([2 => $this->service(2), 5 => $this->service(5)]);
		$coreService = $this->createMock(CoreService::class);
		$disconnected = [];
		$coreService->expects(self::exactly(2))->method('disconnectAccount')
			->willReturnCallback(static function (string $uid, int $sid) use (&$disconnected): void {
				$disconnected[] = [$uid, $sid];
			});
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::never())->method('warning');

		$listener = new UserDeletedListener($logger, $servicesService, $coreService);
		$listener->handle(new UserDeletedEvent($user));

		self::assertSame([['user1', 2], ['user1', 5]], $disconnected);
	}
}
