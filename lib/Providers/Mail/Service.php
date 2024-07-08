<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Sebastian Krupinski <krupinski01@gmail.com>
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
namespace OCA\JMAPC\Providers\Mail;

use OCA\JMAPC\Providers\ServiceLocation;
use OCA\JMAPC\Providers\ServiceIdentityBAuth;
use OCA\JMAPC\Providers\ServiceIdentityOAuth;
use OCA\JMAPC\Service\Remote\RemoteClientService;
use OCP\Mail\Provider\IAddress;
use OCP\Mail\Provider\IMessage;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use OCP\Mail\Provider\IServiceIdentity;
use OCP\Mail\Provider\IServiceLocation;
use Psr\Container\ContainerInterface;

class Service implements IService, IMessageSend {

	protected string $userId;
	protected string $serviceId;
	protected string $serviceLabel;
	protected IAddress $servicePrimaryAddress;
	protected ?array $serviceSecondaryAddress;
	protected ?IServiceLocation $serviceLocation;
	protected ?IServiceIdentity $serviceIdentity;

	protected $commandProcessor = null;

	public function __construct(
		ContainerInterface $container,
		string $uid,
		string $sid,
		string $label,
		IAddress $primaryAddress,
		ServiceIdentityBAuth|ServiceIdentityOAuth $identity = null,
		ServiceLocation $location = null
	) {

		$this->container = $container;
		$this->userId = $uid;
		$this->serviceId = $sid;
		$this->serviceLabel = $label;
		$this->servicePrimaryAddress = $primaryAddress;
		$this->serviceIdentity = $identity;
		$this->serviceLocation = $location;

	}

	/**
	 * An arbitrary unique text string identifying this service
	 *
	 * @since 2024.05.25
	 *
	 * @return string						id of this service (e.g. 1 or service1 or anything else)
	 */
	public function id(): string {

		return $this->serviceId;

	}

	/**
	 * checks or retrieves what capabilites the service has
	 *
	 * @since 2024.05.25
	 *
	 * @param string $ability				required ability e.g. 'MessageSend'
	 *
	 * @return bool|array					true/false if ability is supplied, collection of abilities otherwise
	 */
	public function capable(?string $ability = null): bool | array {

		// define all abilities
		$abilities = [
			'MessageSend' => true,
		];
		// evaluate if required ability was specified
		if (isset($ability)) {
			return (isset($abilities[$ability]) ? (bool) $abilities[$ability] : false);
		} else {
			return $abilities;
		}

	}

	/**
	 * gets the localized human frendly name of this service
	 *
	 * @since 2024.05.25
	 *
	 * @return string						label/name of service (e.g. ACME Company Mail Service)
	 */
	public function getLabel(): string {

		return $this->serviceLabel;

	}

	/**
	 * sets the localized human frendly name of this service
	 *
	 * @since 2024.05.25
	 *
	 * @param string $value					label/name of service (e.g. ACME Company Mail Service)
	 *
	 * @return self                         return this object for command chaining
	 */
	public function setLabel(string $value): self {

		$this->serviceLabel = $value;
		return $this;

	}

	/**
	 * gets service itentity
	 *
	 * @since 2024.05.25
	 *
	 * @return IServiceIdentity				service identity object
	 */
	public function getIdentity(): IServiceIdentity | null {

		return $this->serviceIdentity;

	}

	/**
	 * sets service identity
	 *
	 * @since 2024.05.25
	 *
	 * @param IServiceIdentity $identity	service identity object
	 *
	 * @return self                         return this object for command chaining
	 */
	public function setIdentity(IServiceIdentity $value): self {

		$this->serviceIdentity = $value;
		return $this;
	}

	/**
	 * gets service location
	 *
	 * @since 2024.05.25
	 *
	 * @return IServiceLocation				service location object
	 */
	public function getLocation(): IServiceLocation | null {

		return $this->serviceLocation;

	}

	/**
	 * sets service location
	 *
	 * @since 2024.05.25
	 *
	 * @param IServiceLocation $location	service location object
	 *
	 * @return self                         return this object for command chaining
	 */
	public function setLocation(IServiceLocation $value): self {

		$this->serviceLocation = $value;
		return $this;

	}

	/**
	 * gets the primary mailing address for this service
	 *
	 * @since 2024.05.25
	 *
	 * @return IAddress						mail address object
	 */
	public function getPrimaryAddress(): IAddress {

		// retrieve and return primary service address
		return $this->servicePrimaryAddress;

	}

	/**
	 * sets the primary mailing address for this service
	 *
	 * @since 2024.05.25
	 *
	 * @param IAddress $value				mail address object
	 *
	 * @return self                         return this object for command chaining
	 */
	public function setPrimaryAddress(IAddress $value): self {

		$this->servicePrimaryAddress = $value;
		return $this;

	}

	/**
	 * gets the secondary mailing addresses (aliases) collection for this service
	 *
	 * @since 2024.05.25
	 *
	 * @return array<int, IAddress>			collection of mail address objects
	 */
	public function getSecondaryAddress(): array | null {

		// retrieve and return secondary service addressess (aliases) collection
		return $this->serviceSecondaryAddress;

	}

	/**
	 * sets the secondary mailing addresses (aliases) for this service
	 *
	 * @since 2024.05.25
	 *
	 * @param IAddress ...$value				collection of or one or more mail address objects
	 *
	 * @return self                         	return this object for command chaining
	 */
	public function setSecondaryAddress(IAddress ...$value): self {

		$this->serviceSecondaryAddress = $value;
		return $this;

	}

	
	protected function mailService() {

		if ($this->mailService === null) {
			// construct data store client
			$client = RemoteClientService::createClientFromService($this->serviceLocation, $this->serviceIdentity);
			// load action service
			$this->mailService = $this->container->get(\OCA\JMAPC\Service\MailMessageService::class);
			$this->mailService->initilize($this->userId, $client);
		}
		
		return $this->mailService;
		
	}

	/**
	 * retrieve a list of collections
	 *
	 * @since 2024.05.25
	 *
	 */
	public function collections(string $location, string $scope, array $options = []): array {

		// perform action
		return $this->mailService()->collections($location, $scope, $options);

	}

	public function collectionFetch(string $location, string $id, array $options = []): mixed {
		
		// perform action
		return $this->mailService()->collectionFetch($location, $id, $options);

	}

	public function collectionCreate(string $location, string $label, array $options = []): mixed {

		// perform action
		return $this->mailService()->collectionCreate($location, $label, $options);

	}

	public function collectionUpdate(string $location, string $id, string $label, array $options = []): string {

		// perform action
		return $this->mailService()->collectionUpdate($location, $id, $label, $options);

	}

	public function collectionDelete(string $location, string $id, array $options = []): string {

		// perform action
		return $this->mailService()->collectionDelete($location, $id, $options);

	}

	public function collectionMove(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		// perform action
		return $this->mailService()->collectionMove($sourceLocation, $id, $destinationLocation, $options);

	}

	public function collectionSearch(string $location, string $filter, string $scope, array $options = []): array {

		// perform action
		return $this->mailService()->collectionSearch($location, $filter, $scope, $options);

	}

	/**
	 * Sends an outbound message
	 *
	 * @since 2024.05.25
	 *
	 * @param IMessage $message			mail message object with all required parameters to send a message
	 *
	 * @param array $options			array of options reserved for future use
	 */
	public function sendMessage(IMessage $message, array $option = []): void {

		// perform action
		$this->mailService()->sendMessage($message, $option);

	}

}
