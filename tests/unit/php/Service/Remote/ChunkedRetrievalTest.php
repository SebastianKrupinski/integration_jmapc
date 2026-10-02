<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit\Service\Remote;

use Exception;
use JmapClient\Client;
use JmapClient\Requests\Contacts\ContactGet;
use JmapClient\Requests\Contacts\ContactQuery;
use JmapClient\Responses\ResponseBundle;
use JmapClient\Session\Session;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Service\Remote\ChunkedRetrieval;
use PHPUnit\Framework\TestCase;

class ChunkedRetrievalTest extends TestCase {
	/** @var list<string> */
	private array $ids = [];
	/** @var list<list<array>> method calls of every performed request */
	private array $requests = [];
	private int $serverGetLimit = PHP_INT_MAX;
	private int $serverQueryLimit = PHP_INT_MAX;
	private ?string $failGetWith = null;
	private ?string $failQueryWith = null;
	/** @var list<string> query states returned by successive queries */
	private array $queryStates = [];

	private function subject(array $core = ['maxObjectsInGet' => 500, 'maxCallsInRequest' => 16]): object {
		$client = $this->createMock(Client::class);
		$client->method('sessionData')->willReturn(new Session([
			'capabilities' => ['urn:ietf:params:jmap:core' => $core],
		]));
		$client->method('perform')->willReturnCallback(fn (array $requests): ResponseBundle => $this->serve($requests));

		return new class($client) {
			use ChunkedRetrieval {
				queryAndFetch as public;
				fetchChunked as public;
			}

			public function __construct(
				public Client $dataStore,
			) {
			}
		};
	}

	private function serve(array $requests): ResponseBundle {
		$calls = array_map(static fn ($request): array => json_decode(json_encode($request), true), $requests);
		$this->requests[] = $calls;
		$results = [];
		$responses = [];
		foreach ($calls as [$method, $arguments, $id]) {
			if (str_ends_with($method, '/query')) {
				if ($this->failQueryWith !== null) {
					$responses[] = ['error', ['type' => $this->failQueryWith, 'description' => ''], $id];
					continue;
				}
				$position = $arguments['position'] ?? 0;
				$limit = min($arguments['limit'] ?? PHP_INT_MAX, $this->serverQueryLimit);
				$ids = array_slice($this->ids, $position, $limit === PHP_INT_MAX ? null : $limit);
				$results[$id] = $ids;
				$result = [
					'accountId' => 'a',
					'queryState' => array_shift($this->queryStates) ?? 'q1',
					'position' => $position,
					'ids' => $ids,
				];
				if (!empty($arguments['calculateTotal'])) {
					$result['total'] = count($this->ids);
				}
				if ($this->serverQueryLimit !== PHP_INT_MAX && ($arguments['limit'] ?? PHP_INT_MAX) > $this->serverQueryLimit) {
					$result['limit'] = $this->serverQueryLimit;
				}
				$responses[] = [$method, $result, $id];
				continue;
			}
			if (isset($arguments['#ids']) && !isset($results[$arguments['#ids']['resultOf']])) {
				$responses[] = ['error', ['type' => 'invalidResultReference', 'description' => ''], $id];
				continue;
			}
			$ids = isset($arguments['#ids']) ? $results[$arguments['#ids']['resultOf']] : $arguments['ids'];
			if (count($ids) > $this->serverGetLimit) {
				$responses[] = ['error', ['type' => 'requestTooLarge', 'description' => ''], $id];
				continue;
			}
			if ($this->failGetWith !== null && $ids !== []) {
				$responses[] = ['error', ['type' => $this->failGetWith, 'description' => ''], $id];
				continue;
			}
			$responses[] = [$method, [
				'accountId' => 'a',
				'state' => 's' . count($this->requests),
				'list' => array_map(static fn (string $id): array => ['id' => $id], $ids),
				'notFound' => [],
			], $id];
		}
		return new ResponseBundle(['methodResponses' => $responses, 'sessionState' => 'x']);
	}

	private static function query(): callable {
		return static fn (): ContactQuery => new ContactQuery('a', null, 'urn:ietf:params:jmap:contacts', 'ContactCard');
	}

	private static function get(): callable {
		return static fn (): ContactGet => new ContactGet('a', null, 'urn:ietf:params:jmap:contacts', 'ContactCard');
	}

	/**
	 * @param list<mixed> $objects
	 * @return list<string>
	 */
	private static function objectIds(array $objects): array {
		return array_map(static fn ($object): string => $object->id(), $objects);
	}

	private static function makeIds(int $count): array {
		return array_map(static fn (int $i): string => 'id' . $i, range(1, $count));
	}

	public function testSmallResultIsRetrievedInOneRequest(): void {
		$this->ids = self::makeIds(10);

		$result = $this->subject()->queryAndFetch(self::query(), self::get());

		self::assertCount(1, $this->requests);
		self::assertSame([], $this->requests[0][0][1]['ids']);
		self::assertTrue($this->requests[0][1][1]['calculateTotal']);
		self::assertSame('s1', $result['state']);
		self::assertSame($this->ids, self::objectIds($result['objects']));
	}

	public function testOversizedResultFallsBackToChunkedGets(): void {
		$this->ids = self::makeIds(1004);
		$this->serverGetLimit = 500;

		$result = $this->subject()->queryAndFetch(self::query(), self::get());

		self::assertCount(2, $this->requests);
		self::assertSame([500, 500, 4], array_map(static fn (array $call): int => count($call[1]['ids']), $this->requests[1]));
		self::assertSame('s1', $result['state']);
		self::assertSame($this->ids, self::objectIds($result['objects']));
	}

	public function testRespectsMaxCallsInRequest(): void {
		$this->ids = self::makeIds(1004);
		$this->serverGetLimit = 100;

		$result = $this->subject(['maxObjectsInGet' => 100, 'maxCallsInRequest' => 4])->queryAndFetch(self::query(), self::get());

		self::assertSame($this->ids, self::objectIds($result['objects']));
		foreach (array_slice($this->requests, 1) as $calls) {
			self::assertLessThanOrEqual(4, count($calls));
		}
		self::assertCount(1 + 3, $this->requests);
	}

	public function testHalvesChunkWhenServerRejectsAdvertisedLimit(): void {
		$this->ids = self::makeIds(1004);
		$this->serverGetLimit = 200;
		$subject = $this->subject();

		$result = $subject->queryAndFetch(self::query(), self::get());

		self::assertSame($this->ids, self::objectIds($result['objects']));
		$this->requests = [];
		$subject->fetchChunked(self::makeIds(250), self::get());
		self::assertSame([125, 125], array_map(static fn (array $call): int => count($call[1]['ids']), $this->requests[0]));
	}

	public function testMissingCapabilityUsesDefaults(): void {
		$this->ids = self::makeIds(1004);
		$this->serverGetLimit = 500;

		$result = $this->subject([])->queryAndFetch(self::query(), self::get());

		self::assertSame($this->ids, self::objectIds($result['objects']));
		self::assertCount(2, $this->requests);
	}

	public function testPagesCappedQueryResults(): void {
		$this->ids = self::makeIds(1004);
		$this->serverQueryLimit = 300;

		$result = $this->subject()->queryAndFetch(self::query(), self::get());

		self::assertSame($this->ids, self::objectIds($result['objects']));
		self::assertSame([300, 600, 900], array_map(static fn (array $calls): int => $calls[0][1]['position'], array_slice($this->requests, 1, 3)));
	}

	public function testDoesNotPageWhenCallerSuppliesRange(): void {
		$this->ids = self::makeIds(1004);
		$this->serverQueryLimit = 300;

		$result = $this->subject()->queryAndFetch(self::query(), self::get(), false);

		self::assertCount(1, $this->requests);
		self::assertArrayNotHasKey('calculateTotal', $this->requests[0][1][1]);
		self::assertSame(array_slice($this->ids, 0, 300), self::objectIds($result['objects']));
	}

	public function testRejectsQueryStateChangeWhilePaging(): void {
		$this->ids = self::makeIds(1004);
		$this->serverQueryLimit = 500;
		$this->queryStates = ['q1', 'q2'];

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Query result changed while paging');
		$this->subject()->queryAndFetch(self::query(), self::get());
	}

	public function testUnknownMethodIsReported(): void {
		$this->ids = self::makeIds(3);
		$this->failQueryWith = 'unknownMethod';

		$this->expectException(JmapUnknownMethod::class);
		$this->subject()->queryAndFetch(self::query(), self::get());
	}

	public function testOtherChunkErrorsAreReported(): void {
		$this->failGetWith = 'serverFail';

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('serverFail');
		$this->subject()->fetchChunked(self::makeIds(3), self::get());
	}

	public function testSingleObjectRejectionIsReported(): void {
		$this->serverGetLimit = 0;

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('requestTooLarge');
		$this->subject()->fetchChunked(self::makeIds(3), self::get());
	}
}
