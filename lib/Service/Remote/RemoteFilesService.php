<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Service\Remote;

use Exception;
use JmapClient\Client;
use JmapClient\Requests\Files\NodeGet;
use JmapClient\Requests\Files\NodeQuery;
use JmapClient\Responses\Files\NodeParameters;
use OCA\JMAPC\Objects\File\FileNodeObject;

class RemoteFilesService {
	use ChunkedRetrieval;

	protected Client $dataStore;
	protected string $dataAccount;

	public function initialize(Client $dataStore, ?string $dataAccount = null): void {
		$this->dataStore = $dataStore;
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
		if ($dataAccount === null) {
			$account = $dataStore->sessionAccountDefault('filenode');
			if ($account === null) {
				throw new Exception('Service has no file node account', 1);
			}
			$dataAccount = $account->id();
		}
		$this->dataAccount = $dataAccount;
	}

	/**
	 * list the nodes contained in a node
	 *
	 * @param string|null $location id of the parent node, null for the top level
	 *
	 * @return array{state: string, list: list<FileNodeObject>}
	 */
	public function nodeList(?string $location): array {
		$query = function () use ($location): NodeQuery {
			$r0 = new NodeQuery($this->dataAccount);
			if ($location === null) {
				$r0->filter()->condition('isTopLevel', true);
			} else {
				$r0->filter()->in($location);
			}
			return $r0;
		};
		$get = fn (): NodeGet => new NodeGet($this->dataAccount);
		$result = $this->queryAndFetch($query, $get);
		$list = [];
		foreach ($result['objects'] as $so) {
			if ($so instanceof NodeParameters) {
				$list[] = (new FileNodeObject())->fromJmap($so->parametersRaw());
			}
		}
		return ['state' => $result['state'], 'list' => $list];
	}

	/**
	 * open a stream to the contents of a file node
	 *
	 * @return resource
	 */
	public function nodeContents(FileNodeObject $node) {
		$stream = $this->dataStore->downloadStream(
			$this->dataAccount,
			(string)$node->blob(),
			$node->type() ?? 'application/octet-stream',
			$node->label(),
		);
		$resource = $stream->detach();
		if (!is_resource($resource)) {
			throw new Exception('Blob download did not return a stream', 1);
		}
		return $resource;
	}

}
