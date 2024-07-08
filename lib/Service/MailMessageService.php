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

namespace OCA\JMAPC\Service;

use JmapClient\Client as JmapClient;
use OCA\Providers\Mail\Service;
use OCA\JMAPC\Service\ConfigurationService;
use OCA\JMAPC\Service\CoreService;
use	OCA\JMAPC\Service\Remote\RemoteMailService;

use OCP\Mail\Provider\Message;

class MailMessageService {

	protected string $userId;
	protected JmapClient $remoteStore;
	protected $localMetaStore;
	protected $localBlobStore;

	public function __construct(
		protected ConfigurationService $configuration,
		protected RemoteMailService $remoteMailService
	) {
		$this->configuration = $configuration;
		$this->remoteMailService = $remoteMailService;
	}

	public function initilize(string $uid, JmapClient $remoteStore): void {

		$this->userId = $uid;
		$this->remoteStore = $remoteStore;
		// evaluate if client is connected 
		if (!$this->remoteStore->sessionStatus()) {
			$this->remoteStore->connect();
		}
		// initilize remote service
		$this->remoteMailService->initialize($remoteStore);
		
	}

	public function collections(string $location, string $scope, array $options = []): array {
		
		return $this->remoteMailService->collections($location, $scope);

	}

	public function collectionFetch(string $location, string $id, array $options = []): array {
		
		return $this->remoteMailService->collectionFetch($location, $id);

	}

	public function collectionCreate(string $location, string $label, array $options = []): string {
		
		return $this->remoteMailService->collectionCreate($location, $label);

	}

	public function collectionUpdate(string $location, string $id, string $label, array $options = []): string {

		return $this->remoteMailService->collectionUpdate($location, $id, $label);

	}

	public function collectionDelete(string $location, string $id, array $options = []): string {

		return $this->remoteMailService->collectionDelete($location, $id);

	}

	public function collectionMove(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		return $this->remoteMailService->collectionMove($sourceLocation, $id, $destinationLocation);

	}

	public function collectionSearch(string $location, string $filter, string $scope, array $options = []): array {

		return $this->remoteMailService->collectionSearch($location, $filter, $scope);

	}

	public function messageSend($message, $option): bool {

	}

}
