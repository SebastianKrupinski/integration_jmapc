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

use OCA\JMAPC\Providers\IRange;
use OCA\JMAPC\Providers\IServiceIdentity;
use OCA\JMAPC\Providers\IServiceLocation;
use OCA\JMAPC\Providers\ServiceIdentityBAuth;
use OCA\JMAPC\Providers\ServiceIdentityOAuth;
use OCA\JMAPC\Providers\ServiceLocation;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\IAddress;
use OCP\Mail\Provider\IMessage;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use Psr\Container\ContainerInterface;

class Service implements IService, IMessageSend {

	protected array $serviceSecondaryAddress = [];
	protected array $serviceAbilities = [];

	public function __construct(
		protected ContainerInterface $container,
		protected string $userId = '',
		protected string $serviceId = '',
		protected string $serviceLabel = '',
		protected IAddress $servicePrimaryAddress = new Address(),
		protected ?IServiceIdentity $serviceIdentity = null,
		protected ?IServiceLocation $serviceLocation = null,
	) {
		$this->serviceAbilities = [
			'Collections' => true,
			'CollectionFetch' => true,
			'CollectionCreate' => true,
			'CollectionUpdate' => true,
			'CollectionDelete' => true,
			'CollectionMove' => true,
			'CollectionSearch' => true,
			'MessageFetch' => true,
			'MessageCreate' => true,
			'MessageUpdate' => true,
			'MessageDelete' => true,
			'MessageCopy' => true,
			'MessageMove' => true,
			'MessageForward' => true,
			'MessageRelay' => true,
			'MessageSend' => true,
			'MessageList' => true,
			'MessageSearch' => true,
		];
	}

	/**
	 * An arbitrary unique text string identifying this service
	 *
	 * @since 2024.05.25
	 *
	 * @return string id of this service (e.g. 1 or service1 or anything else)
	 */
	public function id(): string {

		return $this->serviceId;

	}

	/**
	 * checks if a service is able of performing an specific action
	 *
	 * @since 4.0.0
	 *
	 * @param string $value required ability e.g. 'MessageSend'
	 *
	 * @return bool true/false if ability is supplied and found in collection
	 */
	public function capable(string $value): bool {

		// evaluate if required ability exists
		if (isset($this->serviceAbilities[$value])) {
			return (bool)$this->serviceAbilities[$value];
		}
		
		return false;

	}

	/**
	 * retrieves a collection of what actions a service can perfrom
	 *
	 * @since 4.0.0
	 *
	 * @return array collection of abilities otherwise empty collection
	 */
	public function capabilities(): array {

		return $this->serviceAbilities;

	}

	/**
	 * gets the localized human frendly name of this service
	 *
	 * @since 2024.05.25
	 *
	 * @return string label/name of service (e.g. ACME Company Mail Service)
	 */
	public function getLabel(): string {

		return $this->serviceLabel;

	}

	/**
	 * sets the localized human friendly name of this service
	 *
	 * @since 2024.05.25
	 *
	 * @param string $value label/name of service (e.g. ACME Company Mail Service)
	 *
	 * @return self return this object for command chaining
	 */
	public function setLabel(string $value): self {

		$this->serviceLabel = $value;
		return $this;

	}

	/**
	 * construct a new empty identity object
	 *
	 * @since 30.0.0
	 *
	 * @param string $type identity type e.g. BA = Basic, OA = Bearer
	 *
	 * @return IServiceIdentity blank identity object
	 */
	public function initiateIdentity(string $type): IServiceIdentity {

		return match ($type) {
			'BAUTH' => new ServiceIdentityBAuth(),
			'OAUTH' => new ServiceIdentityOAuth(),
		};

	}

	/**
	 * gets service itentity
	 *
	 * @since 2024.05.25
	 *
	 * @return IServiceIdentity service identity object
	 */
	public function getIdentity(): ?IServiceIdentity {

		return $this->serviceIdentity;

	}

	/**
	 * sets service identity
	 *
	 * @since 2024.05.25
	 *
	 * @param IServiceIdentity $identity service identity object
	 *
	 * @return self return this object for command chaining
	 */
	public function setIdentity(IServiceIdentity $value): self {

		$this->serviceIdentity = $value;
		return $this;
	}

	/**
	 * construct a new empty identity object
	 *
	 * @since 30.0.0
	 *
	 * @return IServiceLocation blank identity object
	 */
	public function initiateLocation(): IServiceLocation {

		return new ServiceLocation();

	}
	
	/**
	 * gets service location
	 *
	 * @since 2024.05.25
	 *
	 * @return IServiceLocation service location object
	 */
	public function getLocation(): ?IServiceLocation {

		return $this->serviceLocation;

	}

	/**
	 * sets service location
	 *
	 * @since 2024.05.25
	 *
	 * @param IServiceLocation $location service location object
	 *
	 * @return self return this object for command chaining
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
	 * @return IAddress mail address object
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
	 * @param IAddress $value mail address object
	 *
	 * @return self return this object for command chaining
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
	 * @return array<int, IAddress> collection of mail address objects
	 */
	public function getSecondaryAddresses(): array {

		// retrieve and return secondary service addressess (aliases) collection
		return $this->serviceSecondaryAddress;

	}

	/**
	 * sets the secondary mailing addresses (aliases) for this service
	 *
	 * @since 2024.05.25
	 *
	 * @param IAddress ...$value collection of or one or more mail address objects
	 *
	 * @return self return this object for command chaining
	 */
	public function setSecondaryAddresses(IAddress ...$value): self {

		$this->serviceSecondaryAddress = $value;
		return $this;

	}

	/**
	 * construct a new empty message object
	 *
	 * @since 30.0.0
	 *
	 * @return IMessage blank message object
	 */
	public function initiateMessage(): IMessage {

		return (new Message());

	}

	protected function mailService() {

		if ($this->mailService === null) {
			// construct data store client
			$client = RemoteService::initializeStoreFromService($this->serviceLocation, $this->serviceIdentity);
			// load action service
			$this->mailService = $this->container->get(\OCA\JMAPC\Service\MailMessageService::class);
			$this->mailService->initialize($this->userId, $client);
		}
		
		return $this->mailService;
		
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

	/**
	 * retrieve a list of collections
	 *
	 * @since 2024.05.25
	 *
	 */
	public function collectionList(string $location, string $scope, array $options = []): array {

		// perform action
		return $this->mailService()->collectionList($location, $scope, $options);

	}

	public function collectionSearch(string $location, string $filter, string $scope, array $options = []): array {

		// perform action
		return $this->mailService()->collectionSearch($location, $filter, $scope, $options);

	}

	public function entityFetch(string $location, string $id, string $particulars = 'D', array $options = []): object {

		// perform action
		return $this->mailService()->entityFetch($location, $id, $particulars, $options);

	}

	public function entityCreate(string $location, IMessage $message, array $options = []): string {

		// perform action
		return $this->mailService()->entityCreate($location, $message, $options);

	}

	public function entityUpdate(string $location, string $id, IMessage $message, array $options = []): string {

		// perform action
		return $this->mailService()->entityUpdate($location, $id, $message, $options);

	}

	public function entityDelete(string $location, string $id, array $options = []): string {

		// perform action
		return $this->mailService()->entityDelete($location, $id, $options);

	}

	public function entityCopy(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		// perform action
		return $this->mailService()->entityCopy($sourceLocation, $id, $destinationLocation, $options);

	}

	public function entityMove(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		// perform action
		return $this->mailService()->entityMove($sourceLocation, $id, $destinationLocation, $options);

	}

	public function entityForward(string $location, string $id, IMessage $message, array $options = []): string {

		// perform action
		return $this->mailService()->entityForward($location, $id, $message, $options);

	}

	public function entityReply(string $location, string $id, IMessage $message, array $options = []): string {

		// perform action
		return $this->mailService()->entityReply($location, $id, $message, $options);

	}

	public function entitySend(IMessage $message, array $options = []): string {

		// perform action
		return $this->mailService()->entitySend($message, $options);

	}

	public function entityList(string $location, ?IRange $range = null, ?string $sort = null, string $particulars = 'D', array $options = []): array {

		// perform action
		return $this->mailService()->entityList($location, $range, $sort, $particulars, $options);

	}

	public function entitySearch(string $location, array $filter, ?IRange $range = null, ?string $sort = null, ?string $scope = null, string $particulars = 'D', array $options = []): array {
		
		// perform action
		return $this->mailService()->entitySearch($location, $filter, $range, $sort, $scope, $particulars, $options);

	}

	/**
	 * Sends an outbound message
	 *
	 * @since 2024.05.25
	 *
	 * @param IMessage $message mail message object with all required parameters to send a message
	 *
	 * @param array $options array of options reserved for future use
	 */
	public function sendMessage(IMessage $message, array $options = []): void {

		// perform action
		$this->mailService()->entitySend($message, $options);

	}

	public function blobFetch(string $id, array $options = []): object {

		// perform action
		return $this->mailService()->blobFetch($id, $options);

	}

}
