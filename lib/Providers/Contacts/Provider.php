<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Providers\Contacts;

use OCA\ContactsService\Interfaces\Provider\IProviderBase;
use OCA\ContactsService\Interfaces\Service\IServiceBase;
use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCP\Mail\Provider\IService;

class Provider implements IProviderBase {

	protected string $providerId = 'jmapc';
	protected string $providerLabel = 'JMap Connector';
	protected array $providerAbilities = [];
	private ?array $servicesCache = [];

	public function __construct(
		protected ServicesService $ServicesService,
	) {
		$this->providerAbilities = [
			'ServiceList' => true,
			'ServiceFetch' => true,
			'ServiceCreate' => true,
			'ServiceModify' => true,
			'ServiceDestroy' => true,
		];
	}

	public function jsonSerialize(): mixed {
		return [
			'id' => $this->providerId,
			'label' => $this->providerLabel,
			'capabilities' => $this->providerAbilities,
		];
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

		if (isset($this->providerAbilities[$value])) {
			return (bool)$this->providerAbilities[$value];
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
		return $this->providerAbilities;
	}

	/**
	 * An arbitrary unique text string identifying this provider
	 *
	 * @since 2025.05.01
	 *
	 * @return string id of this provider (e.g. UUID or 'IMAP/SMTP' or anything else)
	 */
	public function id(): string {
		return $this->providerId;
	}

	/**
	 * The localized human friendly name of this provider
	 *
	 * @since 2025.05.01
	 *
	 * @return string label/name of this provider (e.g. Plain Old IMAP/SMTP)
	 */
	public function label(): string {
		return $this->providerLabel;
	}

	/**
	 * Retrieve collection of services for a specific user
	 *
	 * @since 2025.05.01
	 *
	 * @return array<string,Service> collection of service objects
	 */
	public function serviceList(string $uid): array {

		// check if services are cached
		if (isset($this->servicesCache[$uid])) {
			return $this->servicesCache[$uid];
		}
		// retrieve services from store
		try {
			$collection = $this->ServicesService->fetchByUserId($uid);
		} catch (\Throwable $th) {
			return [];
		}
		// convert to service objects
		$this->servicesCache[$uid] = [];
		foreach ($collection as $entry) {
			$this->servicesCache[$uid][$entry->getId()] = $this->serviceInstance($uid, $entry);
		}

		return $this->servicesCache[$uid];

	}

	/**
	 * construct service object instance
	 *
	 * @since 2025.05.01
	 *
	 * @return Service blank service instance
	 */
	protected function serviceInstance(string $uid, ?ServiceEntity $data): Service {
		$service = new Service();
		$service->loadData($uid, $data);
		return $service;
	}

	/**
	 * construct and new blank service instance
	 *
	 * @since 2025.05.01
	 *
	 * @return Service blank service instance
	 */
	public function serviceFresh(string $uid = ''): Service {
		return $this->serviceInstance($uid, null);
	}

	/**
	 * Determine if any services are configured for a specific user
	 *
	 * @since 2025.05.01
	 *
	 * @return bool true if any services are configure for the user
	 */
	public function serviceExtant(string $uid, string $sid): bool {
		return (count($this->serviceList($uid)) > 0);
	}

	/**
	 * Retrieve a service with a specific id
	 *
	 * @since 2025.05.01
	 *
	 * @param string $uid user id
	 * @param string $id service id
	 *
	 * @return Service|null returns service object or null if non found
	 */
	public function serviceFetch(string $uid, string $id): ?Service {

		// check if services are cached
		if (isset($this->servicesCache[$uid][$id])) {
			return $this->servicesCache[$uid][$id];
		}
		// retrieve service from store
		if (is_numeric($id)) {
			try {
				$service = $this->ServicesService->fetchByUserIdAndServiceId($uid, (int)$id);
			} catch (\Throwable $th) {
				return null;
			}
		}
		// convert to service object
		if ($service instanceof ServiceEntity) {
			$this->servicesCache[$uid][$id] = $this->serviceInstance($uid, $service);
		}

		return $this->servicesCache[$uid][$id];

	}

	/**
	 * create a service configuration for a specific user
	 *
	 * @since 2025.05.01
	 *
	 * @param string $uid user id of user to configure service for
	 * @param Service $service service configuration object
	 *
	 * @return string id of created service
	 */
	public function serviceCreate(string $uid, Service $service): string {
		return '';
	}

	/**
	 * modify a service configuration for a specific user
	 *
	 * @since 2025.05.01
	 *
	 * @param string $uid user id of user to configure service for
	 * @param IService $service service configuration object
	 *
	 * @return string id of modified service
	 */
	public function serviceModify(string $uid, Service $service): string {
		return '';
	}

	/**
	 * delete a service configuration for a specific user
	 *
	 * @since 2025.05.01
	 *
	 * @param string $uid user id of user to delete service for
	 * @param Service $service service configuration object
	 *
	 * @return bool status of delete action
	 */
	public function serviceDestroy(string $uid, Service $service): bool {
		return true;
	}

}
