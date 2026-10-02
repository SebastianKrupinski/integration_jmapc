<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use Exception;
use GuzzleHttp\Psr7\Utils;
use JmapClient\Client;
use JmapClient\Responses\ResponseBundle;
use JmapClient\Session\Account;
use JmapClient\Session\Session;
use OCA\JMAPC\Objects\File\FileNodeObject;
use OCA\JMAPC\Service\Remote\RemoteFilesService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RemoteFilesServiceTest extends TestCase {
	/** @var list<array> method calls of every performed request */
	private array $calls = [];
	/** @var array<string, array> nodes the server holds indexed by id */
	private array $nodes = [];

	private Client&MockObject $client;

	protected function setUp(): void {
		parent::setUp();
		$this->client = $this->createMock(Client::class);
		$this->client->method('sessionStatus')->willReturn(true);
		$this->client->method('sessionData')->willReturn(new Session([
			'capabilities' => ['urn:ietf:params:jmap:core' => ['maxObjectsInGet' => 500, 'maxCallsInRequest' => 16]],
		]));
		$this->client->method('perform')->willReturnCallback(fn (array $requests): ResponseBundle => $this->serve($requests));
	}

	private function serve(array $requests): ResponseBundle {
		$results = [];
		$responses = [];
		foreach (array_map(static fn ($request): array => json_decode(json_encode($request), true), $requests) as [$method, $arguments, $id]) {
			$this->calls[] = [$method, $arguments];
			if ($method === 'FileNode/query') {
				$filter = $arguments['filter'] ?? [];
				$ids = array_keys(array_filter($this->nodes, static fn (array $node): bool => isset($filter['isTopLevel'])
					? $node['parentId'] === null
					: $node['parentId'] === ($filter['parentId'] ?? false)));
				$results[$id] = $ids;
				$responses[] = [$method, ['accountId' => 'k', 'queryState' => 'q', 'position' => 0, 'ids' => $ids, 'total' => count($ids)], $id];
				continue;
			}
			$ids = isset($arguments['#ids']) ? $results[$arguments['#ids']['resultOf']] : $arguments['ids'];
			$responses[] = [$method, [
				'accountId' => 'k',
				'state' => 'state-1',
				'list' => array_map(fn (string $nodeId): array => $this->nodes[$nodeId], $ids),
				'notFound' => [],
			], $id];
		}
		return new ResponseBundle(['methodResponses' => $responses, 'sessionState' => 'x']);
	}

	private function service(?string $account = 'k'): RemoteFilesService {
		$service = new RemoteFilesService();
		$service->initialize($this->client, $account);
		return $service;
	}

	public function testInitializeUsesPrimaryFileNodeAccount(): void {
		$this->client->expects(self::once())->method('sessionAccountDefault')->with('filenode')->willReturn(new Account('k', []));
		$this->nodes = ['a' => ['id' => 'a', 'parentId' => null, 'name' => 'Top']];

		$this->service(null)->nodeList(null);

		self::assertSame('k', $this->calls[0][1]['accountId']);
	}

	public function testInitializeFailsWithoutFileNodeAccount(): void {
		$this->client->method('sessionAccountDefault')->willReturn(null);

		$this->expectException(Exception::class);
		$this->service(null);
	}

	public function testNodeListTopLevel(): void {
		$this->nodes = [
			'a' => ['id' => 'a', 'parentId' => null, 'name' => 'Top', 'nodeType' => 'directory'],
			'b' => ['id' => 'b', 'parentId' => 'a', 'name' => 'Inner.txt', 'nodeType' => 'file', 'blobId' => 'B1'],
		];

		$result = $this->service()->nodeList(null);

		self::assertSame('state-1', $result['state']);
		self::assertCount(1, $result['list']);
		self::assertInstanceOf(FileNodeObject::class, $result['list'][0]);
		self::assertSame('Top', $result['list'][0]->label());
		$query = array_values(array_filter($this->calls, static fn (array $call): bool => $call[0] === 'FileNode/query'))[0];
		self::assertSame(['isTopLevel' => true], $query[1]['filter']);
	}

	public function testNodeListChildren(): void {
		$this->nodes = [
			'a' => ['id' => 'a', 'parentId' => null, 'name' => 'Top', 'nodeType' => 'directory'],
			'b' => ['id' => 'b', 'parentId' => 'a', 'name' => 'Inner.txt', 'nodeType' => 'file', 'blobId' => 'B1'],
		];

		$result = $this->service()->nodeList('a');

		self::assertSame(['Inner.txt'], array_map(static fn (FileNodeObject $node) => $node->label(), $result['list']));
		$query = array_values(array_filter($this->calls, static fn (array $call): bool => $call[0] === 'FileNode/query'))[0];
		self::assertSame(['parentId' => 'a'], $query[1]['filter']);
	}

	public function testNodeContentsStreamsBlob(): void {
		$this->client->expects(self::once())->method('downloadStream')
			->with('k', 'B1', 'text/plain', 'notes.txt')
			->willReturn(Utils::streamFor('hello'));
		$node = (new FileNodeObject())->fromJmap(['id' => 'b', 'name' => 'notes.txt', 'blobId' => 'B1', 'type' => 'text/plain']);

		$resource = $this->service()->nodeContents($node);

		self::assertIsResource($resource);
		self::assertSame('hello', stream_get_contents($resource));
	}
}
