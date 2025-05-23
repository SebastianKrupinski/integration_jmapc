<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\JMAPC\Settings;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Service\ConfigurationService;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class UserSettings implements ISettings {

	public function __construct(
		private IInitialState $initialStateService,
		private ConfigurationService $configurationService,
		private string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		
		// retrieve system configuration
		$configuration['system_contacts'] = $this->configurationService->isContactsAppAvailable();
		$configuration['system_events'] = $this->configurationService->isCalendarAppAvailable();
		$configuration['system_tasks'] = $this->configurationService->isTasksAppAvailable();
		
		$this->initialStateService->provideInitialState('system-configuration', $configuration);

		return new TemplateResponse(Application::APP_ID, 'userSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 20;
	}
}
