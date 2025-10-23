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

use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCP\Mail\Provider\IProvider;
use OCP\Mail\Provider\IService;

use Psr\Container\ContainerInterface;

class Provider implements IProvider {

	private ?array $servicesCache = [];

	public function __construct(
		protected ContainerInterface $container,
		protected ServicesService $ServicesService,
	) {
	}

	/**
	 * An arbitrary unique text string identifying this provider
	 *
	 * @since 2024.06.25
	 *
	 * @return string id of this provider (e.g. UUID or 'IMAP/SMTP' or anything else)
	 */
	public function id(): string {
		return 'jmapc';
	}

	/**
	 * The localized human friendly name of this provider
	 *
	 * @since 2024.06.25
	 *
	 * @return string label/name of this provider (e.g. Plain Old IMAP/SMTP)
	 */
	public function label(): string {
		return 'JMAP Connector';
	}

	/**
	 * Determine if any services are configured for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @return bool true if any services are configure for the user
	 */
	public function hasServices(string $uid): bool {
		return (count($this->listServices($uid)) > 0);
	}

	/**
	 * Retrieve collection of services for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @return array<string,IService> collection of service objects
	 */
	public function listServices(string $uid): array {

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
		foreach ($collection as $entry) {
			$this->servicesCache[$uid][$entry->getId()] = $this->instanceService($uid, $entry);
		}

		return $this->servicesCache[$uid];

	}

	/**
	 * Retrieve a service with a specific id
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid user id
	 * @param string $id service id
	 *
	 * @return IService|null returns service object or null if non found
	 */
	public function findServiceById(string $uid, string $id): ?IService {

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
			$this->servicesCache[$uid][$id] = $this->instanceService($uid, $service);
		}

		return $this->servicesCache[$uid][$id];

	}

	/**
	 * Retrieve a service for a specific mail address
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid user id
	 * @param string $address mail address (e.g. test@example.com)
	 *
	 * @return IService returns service object or null if non found
	 */
	public function findServiceByAddress(string $uid, string $address): ?IService {

		try {
			$accounts = $this->ServicesService->fetchByUserIdAndAddress($uid, $address);
		} catch (\Throwable $th) {
			return null;
		}

		if (is_array($accounts) && count($accounts) > 0 && is_array($accounts[0])) {
			return $this->instanceService($uid, $accounts[0]);
		}

		return null;

	}

	protected function instanceService(string $uid, ServiceEntity $service): Service {
		return new Service($this->container, $uid, $service);
	}

	/**
	 * construct and new blank service instance
	 *
	 * @since 30.0.0
	 *
	 * @return IService blank service instance
	 */
	public function initiateService(): IService {
		return $this->freshService();
	}

	/**
	 * construct and new blank service instance
	 *
	 * @since 30.0.0
	 *
	 * @return IService blank service instance
	 */
	public function freshService(string $uid = ''): IService {

		return new Service($this->container, $uid, null);

	}

	/**
	 * create a service configuration for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid user id of user to configure service for
	 * @param IService $service service configuration object
	 *
	 * @return string id of created service
	 */
	public function createService(string $uid, IService $service): string {

		return '';

	}

	/**
	 * modify a service configuration for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid user id of user to configure service for
	 * @param IService $service service configuration object
	 *
	 * @return string id of modified service
	 */
	public function modifyService(string $uid, IService $service): string {

		return '';

	}

	/**
	 * delete a service configuration for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid user id of user to delete service for
	 * @param IService $service service configuration object
	 *
	 * @return bool status of delete action
	 */
	public function deleteService(string $uid, IService $service): bool {

		$this->ServicesService->deleteBy;

	}

}
