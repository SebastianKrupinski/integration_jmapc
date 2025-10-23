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

use OCA\JMAPC\Objects\Mail\MailMessageObject;
use OCA\JMAPC\Providers\IRange;
use OCA\JMAPC\Providers\IServiceIdentity;
use OCA\JMAPC\Providers\IServiceLocationUri;
use OCA\JMAPC\Providers\ServiceIdentityBAuth;
use OCA\JMAPC\Providers\ServiceIdentityOAuth;
use OCA\JMAPC\Providers\ServiceLocation;
use OCA\JMAPC\Service\MailService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\IAddress;
use OCP\Mail\Provider\IMessage;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use OCP\Server;
use Psr\Container\ContainerInterface;

class Service implements IService, IMessageSend {
	protected array $serviceAbilities = [];
	protected ?ServiceEntity $serviceData = null;
	protected ?int $serviceId = null;
	protected ?string $serviceLabel = null;
	protected ?IServiceLocationUri $serviceLocation = null;
	protected ?IServiceIdentity $serviceIdentity = null;
	protected ?IAddress $serviceAddressPrimary = null;
	protected ?array $serviceAddressAlternate = null;
	protected ?MailService $mailService = null;

	public function __construct(
		protected ContainerInterface $container,
		protected string $userId,
		protected ?ServiceEntity $service,
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

		if ($service === null) {
			$service = new ServiceEntity();
			$service->setUid($userId);
			$service->setId(-1);
			$service->setLabel('');
		}

		$this->serviceData = $service;
		$this->serviceId = $this->serviceData->getId();
		$this->serviceLabel = $this->serviceData->getLabel();

	}

	/**
	 * Confirms if specific capability is supported
	 *
	 * @since 1.0.0
	 *
	 * @param string $value required ability e.g. 'MessageSend'
	 *
	 * @return bool
	 */
	public function capable(string $value): bool {

		if (isset($this->serviceAbilities[$value])) {
			return (bool)$this->serviceAbilities[$value];
		}
		return false;

	}

	/**
	 * Lists all supported capabilities
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,bool>
	 */
	public function capabilities(): array {
		return $this->serviceAbilities;
	}

	/**
	 * Unique arbitrary text string identifying this service (e.g. 1 or service1 or anything else)
	 *
	 * @since 1.0.0
	 */
	public function id(): string {
		return (string)$this->serviceId;
	}

	/**
	 * Gets the localized human friendly name of this service (e.g. ACME Company Mail Service)
	 *
	 * @since 1.0.0
	 */
	public function getLabel(): string {
		return (string)$this->serviceLabel;
	}

	/**
	 * Sets the localized human friendly name of this service (e.g. ACME Company Mail Service)
	 *
	 * @since 1.0.0
	 */
	public function setLabel(string $value): self {

		$this->serviceLabel = $value;
		$this->serviceData->setLabel($this->serviceLabel);
		return $this;

	}

	/**
	 * Instances new service identity
	 *
	 * @since 1.0.0
	 *
	 * @param string $type identity type e.g. BAUTH = Basic, OAUTH = Bearer
	 *
	 * @return IServiceIdentity
	 */
	public function freshIdentity(string $type): IServiceIdentity {

		return match ($type) {
			'BAUTH' => new ServiceIdentityBAuth(),
			'OAUTH' => new ServiceIdentityOAuth(),
		};

	}

	/**
	 * Gets service identity
	 *
	 * @since 1.0.0
	 */
	public function getIdentity(): ?IServiceIdentity {

		if ($this->serviceIdentity === null) {
			if ($this->serviceData->getAuth() == 'BA') {
				$identity = new ServiceIdentityBAuth();
				$identity->getIdentity($this->serviceData->getBauthId());
				$identity->setSecret($this->serviceData->getBauthSecret());
				$this->serviceIdentity = $identity;
			}
			if ($this->serviceData->getAuth() == 'OA') {
				$identity = new ServiceIdentityOAuth();
				$identity->setAccessId($this->serviceData->getOauthId());
				$identity->setAccessToken($this->serviceData->getOauthAccessToken());
				$identity->setAccessExpiry($this->serviceData->getOauthExpiry());
				$identity->setRefreshToken($this->serviceData->getOauthRefreshToken());
				$identity->setRefreshLocation($this->serviceData->getOauthRefreshLocation());
				$this->serviceIdentity = $identity;
			}
		}
		return $this->serviceIdentity;

	}

	/**
	 * Sets service identity
	 *
	 * @since 1.0.0
	 */
	public function setIdentity(IServiceIdentity $value): self {

		$this->serviceIdentity = $value;

		if ($value->type() == 'BAUTH' && $value instanceof ServiceIdentityBAuth) {
			$this->serviceData->setAuth('BA');
			$this->serviceData->setBauthId($value->getIdentity());
			$this->serviceData->setBauthSecret($value->getSecret());
		}
		if ($value->type() == 'OAUTH' && $value instanceof ServiceIdentityOAuth) {
			$this->serviceData->setAuth('OA');
			$this->serviceData->setOauthId($value->getAccessId());
			$this->serviceData->setOauthAccessToken($value->getAccessToken());
			$this->serviceData->setOauthExpiry($value->getAccessExpiry());
			$this->serviceData->setOauthRefreshToken($value->getRefreshToken());
			$this->serviceData->setOauthRefreshLocation($value->getRefreshLocation());
		}
		return $this;

	}

	/**
	 * Instances new service location
	 *
	 * @since 1.0.0
	 */
	public function freshLocation(): IServiceLocationUri {
		return new ServiceLocation();
	}

	/**
	 * Gets service location
	 *
	 * @since 1.0.0
	 */
	public function getLocation(): ?IServiceLocationUri {

		if ($this->serviceLocation === null) {
			$location = new ServiceLocation();
			$location->setScheme($this->serviceData->getScheme());
			$location->setHost($this->serviceData->getHost());
			$location->setPath($this->serviceData->getPath());
			$location->setPort($this->serviceData->getPort());
			$this->serviceLocation = $location;
		}
		return $this->serviceLocation;

	}

	/**
	 * Sets service location
	 *
	 * @since 1.0.0
	 */
	public function setLocation(IServiceLocationUri $value): self {

		$this->serviceLocation = $value;

		$this->serviceData->setScheme($value->getScheme());
		$this->serviceData->setHost($value->getHost());
		$this->serviceData->setPath($value->getPath());
		$this->serviceData->setPort($value->getPort());

		return $this;

	}

	/**
	 * Gets the primary mailing address for this service
	 *
	 * @since 1.0.0
	 */
	public function getPrimaryAddress(): IAddress {

		if ($this->serviceAddressPrimary === null) {
			$this->serviceAddressPrimary = new Address($this->serviceData->getAddressPrimary());
		}
		return $this->serviceAddressPrimary;

	}

	/**
	 * Sets the primary mailing address for this service
	 *
	 * @since 1.0.0
	 */
	public function setPrimaryAddress(IAddress $value): self {

		$this->serviceAddressPrimary = $value;
		$this->serviceData->setAddressPrimary($value->getAddress());
		return $this;

	}

	/**
	 * Gets the secondary mailing addresses (aliases) collection for this service
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,IAddress>
	 */
	public function getSecondaryAddresses(): array {

		if ($this->serviceAddressAlternate === null) {
			$this->serviceAddressAlternate = [];
			$data = $this->serviceData->getAddressAlternate();
			$data = json_decode($data);
			foreach ($data as $entry) {
				$this->serviceAddressAlternate[] = new Address($entry);
			}
		}
		return $this->serviceAddressAlternate;

	}

	/**
	 * Sets the secondary mailing addresses (aliases) for this service
	 *
	 * @since 1.0.0
	 */
	public function setSecondaryAddresses(IAddress ...$value): self {

		$this->serviceAddressAlternate = $value;
		$list = [];
		foreach ($value as $entry) {
			$list[] = $entry->getAddress();
		}
		if ($list !== []) {
			$this->serviceData->setAddressAlternate(json_encode($list));
		}
		return $this;

	}

	/**
	 * construct a new empty message object
	 *
	 * @since 1.0.0
	 *
	 * @return MailMessageObject blank message object
	 */
	public function initiateMessage(): IMessage {
		return new MailMessageObject();
	}

	protected function mailService(): MailService {

		// check if mail service is already initialized
		if ($this->mailService === null) {
			// construct data store client
			$client = RemoteService::freshClient($this->service);
			// load action service
			$this->mailService = Server::get(MailService::class);
			$this->mailService->initialize($client);
		}
		return $this->mailService;

	}

	public function collectionList(string $location, string $scope, array $options = []): array {

		return $this->mailService()->collectionList($location, $scope, $options);

	}

	public function collectionFetch(string $location, string $id, array $options = []): mixed {

		return $this->mailService()->collectionFetch($location, $id, $options);

	}

	public function collectionCreate(string $location, string $label, array $options = []): mixed {

		return $this->mailService()->collectionCreate($location, $label, $options);

	}

	public function collectionUpdate(string $location, string $id, string $label, array $options = []): string {

		return $this->mailService()->collectionUpdate($location, $id, $label, $options);

	}

	public function collectionDelete(string $location, string $id, array $options = []): string {

		return $this->mailService()->collectionDelete($location, $id, $options);

	}

	public function collectionMove(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		return $this->mailService()->collectionMove($sourceLocation, $id, $destinationLocation, $options);

	}

	public function entityList(string $location, ?IRange $range = null, ?string $sort = null, string $particulars = 'D', array $options = []): array {

		return $this->mailService()->entityList($location, $range, $sort, $particulars, $options);

	}

	public function entityFetch(string $location, string $id, string $particulars = 'D', array $options = []): object {

		return $this->mailService()->entityFetch($location, $id, $particulars, $options);

	}

	public function entityCreate(string $location, IMessage $message, array $options = []): string {

		return $this->mailService()->entityCreate($location, $message, $options);

	}

	public function entityUpdate(string $location, string $id, IMessage $message, array $options = []): string {

		return $this->mailService()->entityUpdate($location, $id, $message, $options);

	}

	public function entityDelete(string $location, string $id, array $options = []): string {

		return $this->mailService()->entityDelete($location, $id, $options);

	}

	public function entityCopy(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		return $this->mailService()->entityCopy($sourceLocation, $id, $destinationLocation, $options);

	}

	public function entityMove(string $sourceLocation, string $id, string $destinationLocation, array $options = []): string {

		return $this->mailService()->entityMove($sourceLocation, $id, $destinationLocation, $options);

	}

	public function entityForward(string $location, string $id, IMessage $message, array $options = []): string {

		return $this->mailService()->entityForward($location, $id, $message, $options);

	}

	public function entityReply(string $location, string $id, IMessage $message, array $options = []): string {

		return $this->mailService()->entityReply($location, $id, $message, $options);

	}

	public function entitySend(IMessage $message, array $options = []): string {

		return $this->mailService()->entitySend($message, $options);

	}

	/**
	 * Sends an outbound message
	 *
	 * @since 1.0.0
	 *
	 * @param IMessage $message mail message object with all required parameters to send a message
	 *
	 * @param array $options array of options reserved for future use
	 */
	public function sendMessage(IMessage $message, array $options = []): void {

		$this->mailService()->entitySend($message, $options);

	}

	public function blobFetch(string $id, array $options = []): object {

		return $this->mailService()->blobFetch($id, $options);

	}

}
