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

use JmapClient\Client;
use OCA\JMAPC\Providers\IRange;
use OCA\JMAPC\Service\Remote\RemoteMailService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCP\Mail\Provider\IMessage;

class MailService {
	protected Client $dataStore;
	protected RemoteMailService $remoteMailService;
	protected $localMetaStore;
	protected $localBlobStore;
	protected string $servicePrimaryAccount = '';
	protected string $serviceSelectedAccount = '';
	protected array $serviceAvailableAccounts = [];
	protected string $servicePrimaryIdentity = '';
	protected string $serviceSelectedIdentity = '';
	protected array $serviceAvailableIdentities = [];
	protected array $serviceCollectionRoles = [];

	public function __construct(
		protected ConfigurationService $configuration,
	) {	}

	public function initialize(Client $dataStore): void {

		$this->dataStore = $dataStore;
		// evaluate if client is connected
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
		// initialize remote service
		$this->remoteMailService = RemoteService::mailService($dataStore);
		// initialize internal settings
		$this->initializeSession();
		$this->initializeCollectionRoles();

	}

	protected function initializeSession() {

		// retrieve default account
		$this->servicePrimaryAccount = $this->dataStore->sessionAccountDefault('mail');
		$this->serviceSelectedAccount = $this->servicePrimaryAccount;
		// retrieve accounts
		$this->serviceAvailableAccounts = $this->dataStore->sessionAccounts();
		// retrieve identities
		$collection = $this->remoteMailService->identityFetch($this->servicePrimaryAccount);
		foreach ($collection as $entry) {
			$this->serviceAvailableIdentities[$entry->address()] = $entry;
		}

	}

	protected function initializeCollectionRoles() {

		// retrieve collections
		$collectionList = $this->collectionList('', '');
		// find collection with roles
		foreach ($collectionList as $entry) {
			$this->serviceCollectionRoles[$entry->getRole()] = $entry->id();
		}

	}

	public function collectionList(string $location, string $scope, array $options = []): array {

		return $this->remoteMailService->collectionList($this->serviceSelectedAccount, $location, $scope);

	}

	public function collectionFetch(string $location, string $id, array $options = []): object {

		return $this->remoteMailService->collectionFetch($this->serviceSelectedAccount, $location, $id);

	}

	public function collectionCreate(string $location, string $label, array $options = []): string {

		return $this->remoteMailService->collectionCreate($this->serviceSelectedAccount, $location, $label);

	}

	public function collectionUpdate(string $location, string $id, string $label, array $options = []): string {

		return $this->remoteMailService->collectionUpdate($this->serviceSelectedAccount, $location, $id, $label);

	}

	public function collectionDelete(string $location, string $id, array $options = []): string {

		return $this->remoteMailService->collectionDelete($this->serviceSelectedAccount, $location, $id);

	}

	public function collectionMove(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		return $this->remoteMailService->collectionMove($this->serviceSelectedAccount, $sourceLocation, $id, $destinationLocation);

	}

	public function entityList(string $location, ?IRange $range = null, ?string $sort = null, string $particulars = 'D', array $options = []): array {

		return $this->remoteMailService->entityList($this->serviceSelectedAccount, $location, $range, $sort, $particulars);

	}

	public function entityFetch(string $location, string $id, string $particulars = 'D', array $options = []): object {

		return $this->remoteMailService->entityFetch($this->serviceSelectedAccount, $location, $id, $particulars);

	}

	public function entityCreate(string $location, IMessage $message, array $options = []): string {

		return $this->remoteMailService->entityCreate($this->serviceSelectedAccount, $location, $message);

	}

	public function entityUpdate(string $location, string $id, IMessage $message, array $options = []): string {

		return $this->remoteMailService->entityUpdate($this->serviceSelectedAccount, $location, $id, $message);

	}

	public function entityDelete(string $location, string $id, array $options = []): string {

		return $this->remoteMailService->entityDelete($this->serviceSelectedAccount, $location, $id);

	}

	public function entityCopy(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		// perform action
		return $this->remoteMailService->entityCopy($this->serviceSelectedAccount, $sourceLocation, $id, $destinationLocation);

	}

	public function entityMove(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		// perform action
		return $this->remoteMailService->entityMove($this->serviceSelectedAccount, $sourceLocation, $id, $destinationLocation);

	}

	public function entityForward(string $location, string $id, IMessage $message, array $options = []): string {

		// perform action
		return $this->remoteMailService->entityForward($this->serviceSelectedAccount, $location, $id, $message);

	}

	public function entityReply(string $location, string $id, IMessage $message, array $options = []): string {

		// perform action
		return $this->remoteMailService->entityReply($this->serviceSelectedAccount, $location, $id, $message);

	}

	public function entitySend(IMessage $message, array $options = []): string {

		// extract from address
		$from = $message->getFrom();
		// determine if identity exists for this from address
		if (isset($this->serviceAvailableIdentities[$from->getAddress()])) {
			$selectedIdentity = $this->serviceAvailableIdentities[$from->getAddress()]->id();
		}
		// perform action
		return $this->remoteMailService->entitySend($selectedIdentity, $message, $this->serviceCollectionRoles['drafts'], $this->serviceCollectionRoles['sent']);

	}

	public function blobFetch(string $id): object {

		return $this->remoteMailService->blobFetch($this->serviceSelectedAccount, $id);

	}

}
