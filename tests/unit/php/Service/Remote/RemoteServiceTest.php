<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use JmapClient\Authentication\Basic;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class RemoteServiceTest extends TestCase {
	public static function certificateVerification(): array {
		return [[true], [false]];
	}

	#[DataProvider('certificateVerification')]
	public function testFreshClientUsesNativeTransport(bool $verify): void {
		$http = $this->createMock(IClient::class);
		$httpService = $this->createMock(IClientService::class);
		$httpService->expects(self::once())->method('newClient')->willReturn($http);
		$this->overwriteService(IClientService::class, $httpService);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn('{}');
		$response->method('getHeaders')->willReturn(['Content-Type' => ['application/json']]);
		$http->expects(self::once())->method('request')
			->with('GET', 'https://example.org/session', self::callback(static function (array $options) use ($verify): bool {
				return $options['verify'] === $verify
					&& $options['timeout'] === 30
					&& $options['allow_redirects'] === false
					&& !isset($options['allow_local_address']);
			}))
			->willReturn($response);

		$service = new ServiceEntity();
		$service->setLocationProtocol('https');
		$service->setLocationHost('example.org');
		$service->setLocationPort(443);
		$service->setLocationPath('/session');
		$service->setLocationSecurity($verify);
		$service->setAuth('BA');
		$service->setBauthId('user@example.org');
		$service->setBauthSecret('secret');
		$client = RemoteService::freshClient($service);

		self::assertSame('example.org:443', $client->getHost());
		self::assertSame('/session', $client->getDiscoveryPath());
		self::assertInstanceOf(Basic::class, $client->getAuthentication());
		self::assertSame('user@example.org', $client->getAuthentication()->getId());
		self::assertSame(200, $client->transceive('GET', 'https://example.org/session')->getStatusCode());
	}
}
