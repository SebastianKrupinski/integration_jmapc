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
use OCA\JMAPC\Tests\Unit\TestCase;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;

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

	public static function debugModes(): array {
		return [[true], [false]];
	}

	#[DataProvider('debugModes')]
	public function testDebugLoggingUsesDataDirectoryAndServiceId(bool $debug): void {
		$directory = sys_get_temp_dir() . '/jmapc-test-' . bin2hex(random_bytes(8));
		mkdir($directory, 0700);
		$path = $directory . '/jmapc-42.log';
		try {
			$config = $this->createMock(IConfig::class);
			$config->method('getSystemValue')->with('datadirectory', \OC::$SERVERROOT . '/data')->willReturn($directory);
			$this->overwriteService(IConfig::class, $config);
			$http = $this->createMock(IClient::class);
			$httpService = $this->createMock(IClientService::class);
			$httpService->method('newClient')->willReturn($http);
			$this->overwriteService(IClientService::class, $httpService);
			$response = $this->createMock(IResponse::class);
			$response->method('getStatusCode')->willReturn(200);
			$response->method('getBody')->willReturn('{"ok":true}');
			$response->method('getHeaders')->willReturn(['Content-Type' => ['application/json']]);
			$http->method('request')->willReturn($response);
			$service = new ServiceEntity();
			$service->setId(42);
			$service->setDebug($debug);
			$service->setLocationProtocol('https');
			$service->setLocationHost('example.org');
			$service->setLocationPort(443);
			$client = RemoteService::freshClient($service);
			$client->transceive('GET', 'https://example.org/session');
			self::assertSame($debug, file_exists($path));
			if ($debug) {
				$entries = array_map(
					static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
					file($path, FILE_IGNORE_NEW_LINES),
				);
				self::assertCount(2, $entries);
				self::assertSame('JMAPC Request', $entries[0]['message']);
				self::assertSame('JMAPC Response', $entries[1]['message']);
				self::assertSame(['ok' => true], $entries[1]['data']['body']);
			}
		} finally {
			if (file_exists($path)) {
				unlink($path);
			}
			rmdir($directory);
		}
	}
}
