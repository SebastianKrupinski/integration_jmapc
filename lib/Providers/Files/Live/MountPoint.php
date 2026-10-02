<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Files\Live;

use InvalidArgumentException;
use OC\Files\Cache\Watcher;
use OC\Files\Mount\MountPoint as BaseMountPoint;
use OCA\JMAPC\Providers\Files\MountProvider;
use OCA\JMAPC\Service\FilesService;
use OCA\JMAPC\Store\Local\FileCollectionEntity;
use OCP\Files\Mount\IMovableMount;
use OCP\Files\Storage\IStorageFactory;

/**
 * Mount of a JMAP file node account in a user's files
 *
 * Moving or removing the mount in the files app changes only the mount
 * configuration, the remote data is not touched.
 */
class MountPoint extends BaseMountPoint implements IMovableMount {

	public function __construct(
		private FilesService $filesService,
		private FileCollectionEntity $collection,
		?IStorageFactory $loader = null,
	) {
		parent::__construct(
			Storage::class,
			'/' . $collection->getUid() . '/files' . $collection->getLocation(),
			[
				'cid' => $collection->getId(),
				'sid' => $collection->getSid(),
			],
			$loader,
			[
				'encrypt' => false,
				'enable_sharing' => false,
				'previews' => true,
				'readonly' => true,
				'filesystem_check_changes' => Watcher::CHECK_ONCE,
			],
			null,
			MountProvider::class,
		);
	}

	public function getCollection(): FileCollectionEntity {
		return $this->collection;
	}

	#[\Override]
	public function moveMount($target): bool {
		// remove the "/user/files" prefix
		$parts = explode('/', trim($target, '/'), 3);
		try {
			$this->collection = $this->filesService->modify($this->collection->getUid(), $this->collection->getId(), '/' . ($parts[2] ?? ''));
		} catch (InvalidArgumentException) {
			return false;
		}
		$this->setMountPoint($target);
		return true;
	}

	#[\Override]
	public function removeMount(): bool {
		try {
			$this->filesService->delete($this->collection->getUid(), $this->collection->getId());
		} catch (InvalidArgumentException) {
			return false;
		}
		return true;
	}

}
