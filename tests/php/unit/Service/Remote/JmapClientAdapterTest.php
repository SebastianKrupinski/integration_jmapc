<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\NoSeekStream;
use OCA\JMAPC\Service\Remote\JmapClientAdapter;
use OCA\JMAPC\Service\Remote\JmapTransportException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class JmapClientAdapterTest extends TestCase {
	private IClient&MockObject $ncClient;
	private HttpFactory $factory;
	private JmapClientAdapter $client;

	protected function setUp(): void {
		parent::setUp();

		$this->ncClient = $this->createMock(IClient::class);
		$this->factory = new HttpFactory();
		$this->client = new JmapClientAdapter(
			$this->ncClient,
			$this->factory,
			$this->factory,
			['verify' => true],
		);
	}

	/**
	 * @param string|resource $body
	 */
	private function ncResponse(int $status, $body, array $headers): IResponse&MockObject {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		$response->method('getHeaders')->willReturn($headers);
		return $response;
	}

	public function testForwardsRequestAndAdaptsResponse(): void {
		$request = $this->factory->createRequest('POST', 'https://jmap.example.com/api')
			->withHeader('Authorization', 'Basic abc')
			->withBody($this->factory->createStream('{"a":1}'));
		$ncResponse = $this->ncResponse(200, '{"ok":true}', ['Content-Type' => ['application/json']]);

		$captured = [];
		$this->ncClient->expects(self::once())
			->method('request')
			->willReturnCallback(function (string $method, string $uri, array $options) use (&$captured, $ncResponse) {
				$captured = [$method, $uri, $options];
				return $ncResponse;
			});

		$response = $this->client->sendRequest($request);

		self::assertSame('POST', $captured[0]);
		self::assertSame('https://jmap.example.com/api', $captured[1]);
		self::assertFalse($captured[2]['allow_redirects']);
		self::assertFalse($captured[2]['http_errors']);
		self::assertTrue($captured[2]['stream']);
		self::assertTrue($captured[2]['verify']);
		self::assertSame('Basic abc', $captured[2]['headers']['Authorization']);
		self::assertSame('{"a":1}', $captured[2]['body']);
		self::assertSame(200, $response->getStatusCode());
		self::assertSame('{"ok":true}', (string)$response->getBody());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
	}

	public function testOmitsBodyWhenEmpty(): void {
		$request = $this->factory->createRequest('GET', 'https://jmap.example.com/.well-known/jmap');
		$ncResponse = $this->ncResponse(200, '', []);

		$captured = [];
		$this->ncClient->method('request')
			->willReturnCallback(function (string $method, string $uri, array $options) use (&$captured, $ncResponse) {
				$captured = $options;
				return $ncResponse;
			});

		$this->client->sendRequest($request);

		self::assertArrayNotHasKey('body', $captured);
	}

	public function testAdaptsStreamedResourceBody(): void {
		$resource = fopen('php://temp', 'r+');
		fwrite($resource, 'streamed-bytes');
		rewind($resource);
		$this->ncClient->method('request')->willReturn($this->ncResponse(200, $resource, []));

		$response = $this->client->sendRequest(
			$this->factory->createRequest('GET', 'https://jmap.example.com/blob'),
		);

		self::assertSame('streamed-bytes', (string)$response->getBody());
	}

	public function testWrapsClientFailureAsClientException(): void {
		$request = $this->factory->createRequest('GET', 'https://jmap.example.com/api');
		$this->ncClient->method('request')->willThrowException(new \RuntimeException('connection refused'));

		$this->expectException(JmapTransportException::class);
		$this->client->sendRequest($request);
	}

	public function testLogsRedactedTrafficAndPreservesResponseStream(): void {
		$messages = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))->method('debug')->willReturnCallback(static function (string $message, array $context) use (&$messages): void {
			$messages[] = ['message' => $message, 'context' => $context];
		});
		$client = new JmapClientAdapter($this->ncClient, $this->factory, $this->factory, [], $logger);
		$request = $this->factory->createRequest('POST', 'https://user:uri-secret@jmap.example.com/api?access_token=query-secret')
			->withHeader('Authorization', 'Bearer header-secret')
			->withHeader('Cookie', 'session=cookie-secret')
			->withHeader('Content-Type', 'application/json')
			->withBody($this->factory->createStream('{"password":"body-secret","nested":{"accessToken":"nested-secret"},"methodCalls":[["Email/get",{},"0"]]}'));
		$resource = fopen('php://temp', 'r+');
		fwrite($resource, '{"ok":true,"token":"response-secret"}');
		rewind($resource);
		$this->ncClient->method('request')->willReturn($this->ncResponse(200, $resource, ['Content-Type' => ['application/json'], 'Set-Cookie' => ['response-cookie-secret']]));
		$response = $client->sendRequest($request);
		self::assertSame(0, $response->getBody()->tell());
		self::assertSame('{"ok":true,"token":"response-secret"}', $response->getBody()->getContents());
		self::assertSame('JMAPC Request', $messages[0]['message']);
		self::assertSame('POST', $messages[0]['context']['method']);
		self::assertSame('Email/get', $messages[0]['context']['body']['methodCalls'][0][0]);
		self::assertSame('[redacted]', $messages[0]['context']['headers']['Authorization']);
		self::assertSame('[redacted]', $messages[0]['context']['body']['nested']['accessToken']);
		self::assertSame('JMAPC Response', $messages[1]['message']);
		self::assertSame(200, $messages[1]['context']['status']);
		self::assertSame(['ok' => true, 'token' => '[redacted]'], $messages[1]['context']['body']);
		self::assertStringNotContainsString('secret', json_encode($messages));
	}

	public function testLogsFailuresWithoutExceptionPayload(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))->method('debug')->with(
			self::isString(),
			self::callback(static fn (array $context): bool => !str_contains(json_encode($context), 'private-payload')),
		);
		$this->ncClient->method('request')->willThrowException(new \RuntimeException('private-payload'));
		$client = new JmapClientAdapter($this->ncClient, $this->factory, $this->factory, [], $logger);
		$this->expectException(JmapTransportException::class);
		$client->sendRequest($this->factory->createRequest('GET', 'https://jmap.example.com/api'));
	}

	public function testDoesNotConsumeNonSeekableResponsesForLogging(): void {
		$stream = new NoSeekStream($this->factory->createStream('{"ok":true}'));
		$factory = $this->createMock(\Psr\Http\Message\StreamFactoryInterface::class);
		$factory->method('createStream')->willReturn($stream);
		$messages = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('debug')->willReturnCallback(static function (string $message, array $context) use (&$messages): void {
			$messages[] = ['message' => $message, 'context' => $context];
		});
		$client = new JmapClientAdapter($this->ncClient, $this->factory, $factory, [], $logger);
		$this->ncClient->method('request')->willReturn($this->ncResponse(200, '', ['Content-Type' => ['application/json']]));
		$response = $client->sendRequest($this->factory->createRequest('GET', 'https://jmap.example.com/api'));
		self::assertSame('[streamed body]', $messages[1]['context']['body']);
		self::assertSame('{"ok":true}', $response->getBody()->getContents());
	}

	public function testLogsEmptyJsonBodyAsNull(): void {
		$messages = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('debug')->willReturnCallback(static function (string $message, array $context) use (&$messages): void {
			$messages[] = ['message' => $message, 'context' => $context];
		});
		$client = new JmapClientAdapter($this->ncClient, $this->factory, $this->factory, [], $logger);
		$this->ncClient->method('request')->willReturn($this->ncResponse(200, '{"ok":true}', ['Content-Type' => ['application/json']]));
		$client->sendRequest($this->factory->createRequest('GET', 'https://jmap.example.com/api')->withHeader('Content-Type', 'application/json'));
		self::assertNull($messages[0]['context']['body']);
	}

	public static function omittedBodies(): array {
		return [
			['application/octet-stream', 'binary-secret', '[non-JSON body]'],
			['application/json', 'invalid-secret', '[invalid JSON body]'],
			['application/json', str_repeat('x', 1048577), '[body exceeds 1 MiB]'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('omittedBodies')]
	public function testOmitsUnsafeBodies(string $contentType, string $body, string $expected): void {
		$messages = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('debug')->willReturnCallback(static function (string $message, array $context) use (&$messages): void {
			$messages[] = ['message' => $message, 'context' => $context];
		});
		$client = new JmapClientAdapter($this->ncClient, $this->factory, $this->factory, [], $logger);
		$this->ncClient->method('request')->willReturn($this->ncResponse(200, $body, ['Content-Type' => [$contentType]]));
		$response = $client->sendRequest($this->factory->createRequest('GET', 'https://jmap.example.com/api'));
		self::assertSame($expected, $messages[1]['context']['body']);
		self::assertStringNotContainsString($body, json_encode($messages[1]));
		self::assertSame($body, $response->getBody()->getContents());
	}
}
