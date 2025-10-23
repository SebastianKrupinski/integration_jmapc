<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Contacts;

use OCA\ContactsService\Interfaces\Collection\ICollectionBase;
use OCA\ContactsService\Interfaces\Collection\ICollectionMutable;
use OCA\ContactsService\Interfaces\Entity\IEntityMutable;
use OCA\ContactsService\Interfaces\Filter\IFilter;
use OCA\ContactsService\Interfaces\Range\IRange;
use OCA\ContactsService\Interfaces\Service\IServiceCollectionMutable;
use OCA\ContactsService\Interfaces\Service\IServiceEntityMutable;
use OCA\ContactsService\Interfaces\Service\IServiceMutable;
use OCA\ContactsService\Interfaces\Sort\ISort;
use OCA\JMAPC\Providers\IServiceIdentity;
use OCA\JMAPC\Providers\IServiceLocationUri;
use OCA\JMAPC\Service\MailService;
use OCA\JMAPC\Store\Local\ServiceEntity;

class Service  implements IServiceMutable, IServiceCollectionMutable, IServiceEntityMutable {

	protected array $serviceAbilities = [];
	protected ?ServiceEntity $serviceData = null;
	protected ?int $serviceId = null;
	protected ?string $serviceLabel = null;
	protected ?bool $serviceEnabled = true;
	protected ?IServiceLocationUri $serviceLocation = null;
	protected ?IServiceIdentity $serviceIdentity = null;
	protected ?MailService $mailService = null;

	public function __construct() {

		$this->serviceAbilities = [
			'Collections' => true,
			'CollectionFetch' => true,
			'CollectionCreate' => true,
			'CollectionModify' => true,
			'CollectionDestroy' => true,
			'CollectionMove' => true,
			'EntityList' => true,
			'EntityFetch' => true,
			'EntityCreate' => true,
			'EntityModify' => true,
			'EntityDestroy' => true,
			'EntityCopy' => true,
			'EntityMove' => true,
		];

	}

	public function jsonSerialize(): mixed {
		return [
			'id' => $this->serviceId,
			'label' => $this->serviceLabel,
			"enabled" => $this->serviceEnabled,
			'capabilities' => $this->serviceAbilities,
		];
	}

	public function loadData(string $userId, ?ServiceEntity $service) {

		if ($service === null) {
			$service = new ServiceEntity();
			$service->setUid($userId);
			$service->setId(-1);
			$service->setLabel('');
		}

		$this->serviceData = $service;
		$this->serviceId = $this->serviceData->getId();
		$this->serviceLabel = $this->serviceData->getLabel();

		return $this;
	}

	/**
	 * Confirms if specific capability is supported
	 *
	 * @since 1.0.0
	 *
	 * @param string $value required ability e.g. 'EntityList'
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
	 * Gets the active status of this service
	 *
	 * @since 1.0.0
	 */
	public function getEnabled(): bool {
		return (bool)$this->serviceEnabled;
	}

	/**
	 * Sets the active status of this service
	 *
	 * @since 1.0.0
	 */
	public function setEnabled(bool $value): self {
		$this->serviceEnabled = $value;
		$this->serviceData->setEnabled($this->serviceLabel);
		return $this;
	}

	public function collectionList(?IFilter $filter = null, ?ISort $sort = null): array {
		return [];
	}

	public function collectionExtant(string $id): bool {
		return true;
	}

	public function collectionFetch(string $id): Collection {
		return new Collection();
	}

	public function collectionCreate(string $location, ICollectionMutable $collection): Collection {
		return new Collection();
	}

	public function collectionModify(string $id, ICollectionMutable $collection): Collection {
		return new Collection();
	}

	public function collectionDestroy(string $id): object {
		return new Collection();
	}

	public function collectionMove(string $location, ICollectionBase $collection): Collection {
		return new Collection();
	}

	public function entityList(string $location, ?IFilter $filter = null, ?ISort $sort = null, ?IRange $range = null, ?array $elements = null): array {
		return [];
	}

	public function entityDelta(string $location, string $signature): array {
		return [];
	}

	public function entityFetch(string $location, string $id): Entity {
		return new Entity();
	}

	public function entityCreate(string $location, IEntityMutable $entity): Entity {
		return new Entity();
	}

	public function entityModify(string $location, string $id, IEntityMutable $entity): Entity {
		return new Entity();
	}

	public function entityDestory(string $location, string $id): string {
		return '';
	}

	public function entityCopy(string $location,IEntityMutable $entity): Entity {
		return new Entity();
	}

	public function entityMove(string $location,IEntityMutable $entity): Entity {
		return new Entity();
	}

}
