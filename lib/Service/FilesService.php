<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Service;

use InvalidArgumentException;
use OCA\JMAPC\Objects\AuthenticationTypes;
use OCA\JMAPC\Providers\Files\Live\Storage;
use OCA\JMAPC\Providers\Files\StorageCleaner;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\FileCollectionEntity;
use OCA\JMAPC\Store\Local\FilesStore;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCA\JMAPC\Utile\UUID;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class FilesService {

	public const MODE_LIVE = 'live';
	public const MODES = [self::MODE_LIVE];

	public function __construct(
		private FilesStore $_Store,
		private ServicesService $ServicesService,
		private IRootFolder $rootFolder,
		private IFilenameValidator $filenameValidator,
		private StorageCleaner $storageCleaner,
	) {
	}

	/**
	 * @return array<int,FileCollectionEntity>
	 */
	public function fetchByUserId(string $uid): array {
		$filter = $this->_Store->collectionListFilter();
		$filter->condition('uid', $uid);
		return $this->_Store->collectionList($filter);
	}

	/**
	 * retrieve the collections of a user that should be mounted
	 *
	 * @return array<int,FileCollectionEntity>
	 */
	public function fetchActiveByUserId(string $uid): array {
		return $this->_Store->collectionListActive($uid, self::MODE_LIVE);
	}

	public function fetchByUserIdAndCollectionId(string $uid, int $id): ?FileCollectionEntity {
		$collection = $this->_Store->collectionFetch($id);
		if ($collection === null || $collection->getUid() !== $uid) {
			return null;
		}
		return $collection;
	}

	/**
	 * create a collection mounting the files of a service owned by the user
	 *
	 * @throws InvalidArgumentException when the collection can not be created
	 */
	public function create(string $uid, int $sid, string $location, string $mode = self::MODE_LIVE, ?string $label = null): FileCollectionEntity {
		$service = $this->ServicesService->fetchByUserIdAndServiceId($uid, $sid);
		if ($service === null) {
			throw new InvalidArgumentException('Service not found');
		}
		if ($service->getAuth() === AuthenticationTypes::Bearer->value) {
			throw new InvalidArgumentException('Services using OAuth can not be mounted');
		}
		if (count($this->fetchByServiceId($sid)) > 0) {
			throw new InvalidArgumentException('Service is already mounted');
		}
		$this->validateMode($mode);
		$location = $this->normalizeLocation($location);
		$this->validateLocation($uid, $location);
		if (!$this->remoteCapable($service)) {
			throw new InvalidArgumentException('Service does not support files');
		}

		$collection = new FileCollectionEntity();
		$collection->setUid($uid);
		$collection->setSid($sid);
		$collection->setUuid(UUID::v4());
		$collection->setMode($mode);
		$collection->setLocation($location);
		$collection->setLabel($label ?? $service->getLabel());
		$collection->setVisible(true);
		return $this->_Store->collectionCreate($collection);
	}

	/**
	 * modify a collection owned by the user, null parameters are left unchanged
	 *
	 * @throws InvalidArgumentException when the collection can not be modified
	 */
	public function modify(string $uid, int $id, ?string $location = null, ?string $mode = null, ?string $label = null, ?bool $visible = null): FileCollectionEntity {
		$collection = $this->fetchByUserIdAndCollectionId($uid, $id);
		if ($collection === null) {
			throw new InvalidArgumentException('Mount not found');
		}
		if ($mode !== null) {
			$this->validateMode($mode);
			$collection->setMode($mode);
		}
		if ($location !== null) {
			$location = $this->normalizeLocation($location);
			if ($location !== $collection->getLocation()) {
				$this->validateLocation($uid, $location, $id);
				$collection->setLocation($location);
			}
		}
		if ($label !== null) {
			$collection->setLabel($label);
		}
		if ($visible !== null) {
			$collection->setVisible($visible);
		}
		return $this->_Store->collectionModify($collection);
	}

	/**
	 * delete a collection owned by the user, the remote data is not touched
	 *
	 * @throws InvalidArgumentException when the collection does not exist
	 */
	public function delete(string $uid, int $id): void {
		$collection = $this->fetchByUserIdAndCollectionId($uid, $id);
		if ($collection === null) {
			throw new InvalidArgumentException('Mount not found');
		}
		$this->_Store->collectionDelete($collection);
		$this->storageCleaner->remove(Storage::storageId($id));
	}

	public function deleteByService(int $sid): int {
		$collections = $this->fetchByServiceId($sid);
		$count = $this->_Store->collectionDeleteByService($sid);
		$this->removeStorages($collections);
		return $count;
	}

	public function deleteByUser(string $uid): int {
		$collections = $this->fetchByUserId($uid);
		$count = $this->_Store->collectionDeleteByUser($uid);
		$this->removeStorages($collections);
		return $count;
	}

	/**
	 * normalize a location to the form "/segment/segment"
	 *
	 * @throws InvalidArgumentException when the location is the root or contains an invalid segment
	 */
	public function normalizeLocation(string $location): string {
		$segments = array_values(array_filter(explode('/', $location), static fn (string $segment) => $segment !== ''));
		if ($segments === []) {
			throw new InvalidArgumentException('Mount location can not be the root folder');
		}
		foreach ($segments as $segment) {
			try {
				$this->filenameValidator->validateFilename($segment);
			} catch (InvalidPathException $e) {
				throw new InvalidArgumentException('Mount location is invalid: ' . $e->getMessage(), 0, $e);
			}
		}
		return '/' . implode('/', $segments);
	}

	/**
	 * @return array<int,FileCollectionEntity>
	 */
	protected function fetchByServiceId(int $sid): array {
		$filter = $this->_Store->collectionListFilter();
		$filter->condition('sid', $sid);
		return $this->_Store->collectionList($filter);
	}

	/**
	 * @param array<int,FileCollectionEntity> $collections
	 */
	protected function removeStorages(array $collections): void {
		foreach ($collections as $collection) {
			$this->storageCleaner->remove(Storage::storageId($collection->getId()));
		}
	}

	protected function validateMode(string $mode): void {
		if (!in_array($mode, self::MODES, true)) {
			throw new InvalidArgumentException('Mount mode is not supported');
		}
	}

	/**
	 * ensure a normalized location can hold a new mount
	 *
	 * @param int|null $excludeId collection to ignore when checking for overlapping mounts
	 *
	 * @throws InvalidArgumentException when the location is not available
	 */
	protected function validateLocation(string $uid, string $location, ?int $excludeId = null): void {
		foreach ($this->fetchByUserId($uid) as $collection) {
			if ($collection->getId() === $excludeId) {
				continue;
			}
			$existing = $collection->getLocation();
			if ($existing === $location
				|| str_starts_with($location, $existing . '/')
				|| str_starts_with($existing, $location . '/')) {
				throw new InvalidArgumentException('Mount location overlaps another mount');
			}
		}

		$userFolder = $this->rootFolder->getUserFolder($uid);
		if ($userFolder->nodeExists($location)) {
			throw new InvalidArgumentException('Mount location already exists');
		}
		$parentPath = dirname($location);
		try {
			$parent = $parentPath === '/' ? $userFolder : $userFolder->get($parentPath);
		} catch (NotFoundException) {
			throw new InvalidArgumentException('Mount location parent folder does not exist');
		}
		if (!$parent instanceof Folder) {
			throw new InvalidArgumentException('Mount location parent is not a folder');
		}
		if ($parent->getMountPoint()->getMountPoint() !== $userFolder->getMountPoint()->getMountPoint()) {
			throw new InvalidArgumentException('Mount location can not be inside another storage');
		}
	}

	/**
	 * determine if the remote service supports the file node capability
	 */
	protected function remoteCapable(ServiceEntity $service): bool {
		$client = RemoteService::freshClient($service);
		$client->connect();
		return $client->sessionCapable('filenode');
	}

}
