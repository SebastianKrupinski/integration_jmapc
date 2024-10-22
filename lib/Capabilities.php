<?php
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC;

use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {
	
	public function __construct(
		private IAppManager $appManager
	) {}

	/**
	 * Function an app uses to return the capabilities
	 *
	 * @return array{deck: array{version: string, canCreateBoards: bool, apiVersions: array<string>}}
	 * @since 8.2.0
	 */
	public function getCapabilities() {
		return [
			'integration_jmapc' => [
				'version' => '1.0'
			]
		];
	}
	
}
