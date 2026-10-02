<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Files;

use OCA\JMAPC\Providers\Files\Live\MountPoint;
use OCA\JMAPC\Service\FilesService;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;

/**
 * Provides the JMAP file mounts of a user, reads the configuration only and
 * never contacts the remote service
 */
class MountProvider implements IMountProvider {

	public function __construct(
		private FilesService $filesService,
	) {
	}

	/**
	 * @return list<MountPoint>
	 */
	#[\Override]
	public function getMountsForUser(IUser $user, IStorageFactory $loader): array {
		$mounts = [];
		foreach ($this->filesService->fetchActiveByUserId($user->getUID()) as $collection) {
			$mounts[] = new MountPoint($this->filesService, $collection, $loader);
		}
		return $mounts;
	}

}
