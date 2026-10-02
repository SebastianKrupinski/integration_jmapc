<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Service\Remote;

use Exception;
use JmapClient\Client;
use JmapClient\Requests\RequestGet;
use JmapClient\Requests\RequestQuery;
use JmapClient\Responses\ResponseException;
use JmapClient\Responses\ResponseQuery;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;

/**
 * Retrieves objects in batches that respect the server's advertised
 * maxObjectsInGet and maxCallsInRequest limits.
 *
 * @property Client $dataStore
 */
trait ChunkedRetrieval {
	private const DEFAULT_OBJECTS_IN_GET = 500;
	private const DEFAULT_CALLS_IN_REQUEST = 16;

	private ?int $objectsInGetLimit = null;

	/**
	 * query ids and retrieve the matching objects
	 *
	 * The returned state is captured before the query runs, so changes made
	 * while the objects are retrieved are reported by the next delta.
	 *
	 * @param callable(): RequestQuery $query constructs the query with filter and sort applied
	 * @param callable(): RequestGet $get constructs a get request with properties applied
	 * @param bool $complete retrieve every page when the server caps the query result
	 *
	 * @return array{state: string, objects: list<mixed>}
	 */
	protected function queryAndFetch(callable $query, callable $get, bool $complete = true): array {
		$r0 = $get()->target();
		$r1 = $query();
		if ($complete) {
			$r1->tally(true);
		}
		$r2 = $get()->targetFromRequest($r1, '/ids');
		$bundle = $this->dataStore->perform([$r0, $r1, $r2]);

		$snapshot = $bundle->response(0);
		$page = $bundle->response(1);
		$this->assertResponse($snapshot);
		$this->assertResponse($page);

		$ids = $page->list();
		$objects = [];
		$fetched = 0;
		$response = $bundle->response(2);
		if (!$response instanceof ResponseException) {
			$objects = $response->objects();
			$fetched = count($ids);
		} elseif ($response->type() !== ResponseException::REQUEST_TOO_LARGE) {
			$this->assertResponse($response);
		}

		if ($complete) {
			array_push($ids, ...$this->queryRemaining($query, $page));
		}

		array_push($objects, ...$this->fetchChunked(array_slice($ids, $fetched), $get));

		return ['state' => $snapshot->state(), 'objects' => $objects];
	}

	/**
	 * retrieve objects by id in as many requests as the server limits require
	 *
	 * @param list<string> $identifiers
	 * @param callable(): RequestGet $get constructs a get request with properties applied
	 *
	 * @return list<mixed>
	 */
	protected function fetchChunked(array $identifiers, callable $get): array {
		$objects = [];
		$pending = array_values($identifiers);
		while ($pending !== []) {
			$size = $this->objectsInGetLimit();
			$chunks = array_slice(array_chunk($pending, $size), 0, $this->callsInRequestLimit());
			$bundle = $this->dataStore->perform(array_map(
				static fn (array $chunk): RequestGet => $get()->target(...$chunk),
				$chunks,
			));
			foreach ($chunks as $index => $chunk) {
				$response = $bundle->response($index);
				if ($response instanceof ResponseException && $response->type() === ResponseException::REQUEST_TOO_LARGE && $size > 1) {
					$this->objectsInGetLimit = intdiv($size, 2);
					continue 2;
				}
				$this->assertResponse($response);
				array_push($objects, ...$response->objects());
				$pending = array_slice($pending, count($chunk));
			}
		}
		return $objects;
	}

	/**
	 * @param callable(): RequestQuery $query
	 *
	 * @return list<string>
	 */
	private function queryRemaining(callable $query, ResponseQuery $first): array {
		$ids = [];
		$position = $first->position() + count($first->list());
		$page = $first;
		while ($this->hasMorePages($page, $position)) {
			$request = $query()->limitAbsolute($position)->tally(true);
			$page = $this->dataStore->perform([$request])->response(0);
			$this->assertResponse($page);
			if ($page->state() !== $first->state()) {
				throw new Exception('Query result changed while paging, retry the operation', 1);
			}
			array_push($ids, ...$page->list());
			$position += count($page->list());
		}
		return $ids;
	}

	private function hasMorePages(ResponseQuery $page, int $position): bool {
		if ($page->list() === []) {
			return false;
		}
		if ($page->total() !== null) {
			return $position < $page->total();
		}
		return $page->limit() !== null;
	}

	private function objectsInGetLimit(): int {
		return $this->objectsInGetLimit ??= $this->positiveOr(
			$this->dataStore->sessionData()?->coreCapability()?->maxObjectsInGet(),
			self::DEFAULT_OBJECTS_IN_GET,
		);
	}

	private function callsInRequestLimit(): int {
		return $this->positiveOr(
			$this->dataStore->sessionData()?->coreCapability()?->maxCallsInRequest(),
			self::DEFAULT_CALLS_IN_REQUEST,
		);
	}

	private function positiveOr(?int $value, int $default): int {
		return $value !== null && $value > 0 ? $value : $default;
	}

	private function assertResponse(mixed $response): void {
		if (!$response instanceof ResponseException) {
			return;
		}
		if ($response->type() === ResponseException::UNKNOWN_METHOD) {
			throw new JmapUnknownMethod($response->description(), 1);
		}
		throw new Exception($response->type() . ': ' . $response->description(), 1);
	}
}
