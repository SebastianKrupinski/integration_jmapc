<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Files\Live;

use Icewind\Streams\IteratorDirectory;
use OC\Files\Storage\Common;
use OCA\JMAPC\Objects\File\FileNodeObject;
use OCA\JMAPC\Service\Remote\RemoteFilesService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Service\ServicesService;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\StorageNotAvailableException;
use OCP\Server;
use Throwable;

/**
 * Storage that reads a JMAP file node account on every access
 *
 * Paths are resolved to nodes by listing each parent folder, listings are kept
 * for the lifetime of the storage instance, which is a single request.
 */
class Storage extends Common {

	private const MIME_DIRECTORY = 'httpd/unix-directory';

	private int $collectionId;
	private int $serviceId;
	private ?RemoteFilesService $remote;
	/** @var array<string, array<string, FileNodeObject>|null> folder listings indexed by path, null for missing folders */
	private array $listings = [];
	/** @var array<string, string> remote state of each listing */
	private array $states = [];

	/**
	 * @param array{cid: int, sid: int, remote?: RemoteFilesService} $parameters
	 */
	public function __construct(array $parameters) {
		parent::__construct($parameters);
		$this->collectionId = (int)$parameters['cid'];
		$this->serviceId = (int)$parameters['sid'];
		$this->remote = $parameters['remote'] ?? null;
	}

	public static function storageId(int $collectionId): string {
		return 'jmapc::files::' . $collectionId;
	}

	#[\Override]
	public function getId(): string {
		return self::storageId($this->collectionId);
	}

	#[\Override]
	public function test(): bool {
		try {
			$this->remote();
			return true;
		} catch (StorageNotAvailableException) {
			return false;
		}
	}

	#[\Override]
	public function opendir(string $path) {
		$listing = $this->listing($path);
		if ($listing === null) {
			return false;
		}
		return IteratorDirectory::wrap(array_map('strval', array_keys($listing)));
	}

	#[\Override]
	public function getDirectoryContent(string $directory): \Traversable {
		$directory = $this->normalize($directory);
		$listing = $this->listing($directory);
		if ($listing === null) {
			return;
		}
		foreach ($listing as $name => $node) {
			yield $this->metaFromNode($this->join($directory, (string)$name), $node, $this->states[$directory]);
		}
	}

	#[\Override]
	public function getMetaData(string $path): ?array {
		$path = $this->normalize($path);
		if ($path === '') {
			return $this->rootMetaData();
		}
		$node = $this->node($path);
		if ($node === null) {
			return null;
		}
		return $this->metaFromNode($path, $node, $this->states[$this->parent($path)]);
	}

	#[\Override]
	public function stat(string $path): array|false {
		return $this->getMetaData($path) ?? false;
	}

	#[\Override]
	public function filetype(string $path): string|false {
		$meta = $this->getMetaData($path);
		if ($meta === null) {
			return false;
		}
		return $meta['mimetype'] === self::MIME_DIRECTORY ? 'dir' : 'file';
	}

	#[\Override]
	public function file_exists(string $path): bool {
		$path = $this->normalize($path);
		return $path === '' || $this->node($path) !== null;
	}

	#[\Override]
	public function getPermissions(string $path): int {
		return $this->getMetaData($path)['permissions'] ?? 0;
	}

	#[\Override]
	public function isReadable(string $path): bool {
		return ($this->getPermissions($path) & Constants::PERMISSION_READ) !== 0;
	}

	#[\Override]
	public function isUpdatable(string $path): bool {
		return ($this->getPermissions($path) & Constants::PERMISSION_UPDATE) !== 0;
	}

	#[\Override]
	public function isCreatable(string $path): bool {
		return ($this->getPermissions($path) & Constants::PERMISSION_CREATE) !== 0;
	}

	#[\Override]
	public function isDeletable(string $path): bool {
		return ($this->getPermissions($path) & Constants::PERMISSION_DELETE) !== 0;
	}

	#[\Override]
	public function isSharable(string $path): bool {
		return false;
	}

	#[\Override]
	public function getETag(string $path): string|false {
		return $this->getMetaData($path)['etag'] ?? false;
	}

	/**
	 * folders are always reported as updated so their listing is read again,
	 * the remote does not change a folder when its children change
	 */
	#[\Override]
	public function hasUpdated(string $path, int $time): bool {
		$meta = $this->getMetaData($path);
		if ($meta === null || $meta['mimetype'] === self::MIME_DIRECTORY) {
			return true;
		}
		return $meta['mtime'] > $time;
	}

	#[\Override]
	public function free_space(string $path): int|float|false {
		return FileInfo::SPACE_UNKNOWN;
	}

	#[\Override]
	public function fopen(string $path, string $mode) {
		if ($mode !== 'r' && $mode !== 'rb') {
			return false;
		}
		$node = $this->node($this->normalize($path));
		if ($node === null || $node->isDirectory()) {
			return false;
		}
		try {
			return $this->remote()->nodeContents($node);
		} catch (StorageNotAvailableException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new StorageNotAvailableException('Failed to download ' . $path, StorageNotAvailableException::STATUS_ERROR, $e);
		}
	}

	#[\Override]
	public function mkdir(string $path): bool {
		return false;
	}

	#[\Override]
	public function rmdir(string $path): bool {
		return false;
	}

	#[\Override]
	public function unlink(string $path): bool {
		return false;
	}

	#[\Override]
	public function touch(string $path, ?int $mtime = null): bool {
		return false;
	}

	#[\Override]
	public function rename(string $source, string $target): bool {
		return false;
	}

	#[\Override]
	public function copy(string $source, string $target): bool {
		return false;
	}

	/**
	 * @throws StorageNotAvailableException when the remote service can not be reached
	 */
	private function remote(): RemoteFilesService {
		if ($this->remote === null) {
			try {
				$service = Server::get(ServicesService::class)->fetch($this->serviceId);
				$client = RemoteService::freshClient($service);
				$this->remote = RemoteService::filesService($client);
			} catch (Throwable $e) {
				throw new StorageNotAvailableException('JMAP service ' . $this->serviceId . ' is not available', StorageNotAvailableException::STATUS_ERROR, $e);
			}
		}
		return $this->remote;
	}

	/**
	 * retrieve the children of a folder indexed by name
	 *
	 * @return array<string, FileNodeObject>|null null when the path is not a folder
	 * @throws StorageNotAvailableException when the remote service can not be reached
	 */
	private function listing(string $path): ?array {
		$path = $this->normalize($path);
		if (array_key_exists($path, $this->listings)) {
			return $this->listings[$path];
		}
		$location = null;
		if ($path !== '') {
			$node = $this->node($path);
			if ($node === null || !$node->isDirectory()) {
				return $this->listings[$path] = null;
			}
			$location = $node->id();
		}
		try {
			$result = $this->remote()->nodeList($location);
		} catch (StorageNotAvailableException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new StorageNotAvailableException('Failed to list ' . $path, StorageNotAvailableException::STATUS_ERROR, $e);
		}
		$listing = [];
		foreach ($result['list'] as $node) {
			$listing[$node->label()] = $node;
		}
		$this->states[$path] = $result['state'];
		return $this->listings[$path] = $listing;
	}

	private function node(string $path): ?FileNodeObject {
		if ($path === '') {
			return null;
		}
		$listing = $this->listing($this->parent($path));
		return $listing[basename($path)] ?? null;
	}

	private function rootMetaData(): array {
		$listing = $this->listing('');
		$mtime = 0;
		foreach ($listing as $node) {
			$mtime = max($mtime, $node->modified());
		}
		return [
			'name' => '',
			'mimetype' => self::MIME_DIRECTORY,
			'mtime' => $mtime,
			'storage_mtime' => $mtime,
			'size' => -1,
			'etag' => md5('root|' . $this->states['']),
			'permissions' => Constants::PERMISSION_READ | Constants::PERMISSION_CREATE | Constants::PERMISSION_UPDATE,
		];
	}

	/**
	 * @param string $state remote state of the listing the node was read from
	 */
	private function metaFromNode(string $path, FileNodeObject $node, string $state): array {
		$mtime = $node->modified();
		if ($node->isDirectory()) {
			$mimetype = self::MIME_DIRECTORY;
			$size = -1;
			// folders carry no change marker for their children, the account state stands in for it
			$etag = md5($node->id() . '|' . $mtime . '|' . $state);
		} else {
			$mimetype = $node->type() ?? Server::get(IMimeTypeDetector::class)->detectPath($node->label());
			$size = $node->size();
			$etag = md5($node->id() . '|' . $node->blob() . '|' . $mtime);
		}
		return [
			'name' => basename($path),
			'mimetype' => $mimetype,
			'mtime' => $mtime,
			'storage_mtime' => $mtime,
			'size' => $size,
			'etag' => $etag,
			'permissions' => $this->permissionsFromNode($node),
		];
	}

	private function permissionsFromNode(FileNodeObject $node): int {
		$permissions = 0;
		if ($node->may('mayRead')) {
			$permissions |= Constants::PERMISSION_READ;
		}
		if ($node->may('mayModifyContent') || $node->may('mayRename')) {
			$permissions |= Constants::PERMISSION_UPDATE;
		}
		if ($node->isDirectory() && $node->may('mayAddChildren')) {
			$permissions |= Constants::PERMISSION_CREATE;
		}
		if ($node->may('mayDelete')) {
			$permissions |= Constants::PERMISSION_DELETE;
		}
		return $permissions;
	}

	private function normalize(string $path): string {
		$path = trim($path, '/');
		return $path === '.' ? '' : $path;
	}

	private function parent(string $path): string {
		return $this->normalize(dirname($path));
	}

	private function join(string $directory, string $name): string {
		return $directory === '' ? $name : $directory . '/' . $name;
	}

}
