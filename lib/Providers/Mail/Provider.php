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

use OCA\JMAPC\Account;
use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Providers\ServiceLocation;
use OCA\JMAPC\Providers\ServiceIdentityBAuth;
use OCA\JMAPC\Providers\ServiceIdentityOAuth;

use OCP\Mail\Provider\Address as MailAddress;
use OCP\Mail\Provider\IProvider;
use OCP\Mail\Provider\IService;

use Psr\Container\ContainerInterface;

class Provider implements IProvider {

	private ContainerInterface $container;
	private ServicesService $ServicesService;
	private ?array $ServiceCollection = [];

	public function __construct(
		ContainerInterface $container,
		ServicesService $ServicesService
	) {
		
		$this->container = $container;
		$this->ServicesService = $ServicesService;

	}

	/**
	 * An arbitrary unique text string identifying this provider
	 *
	 * @since 2024.06.25
	 *
	 * @return string				id of this provider (e.g. UUID or 'IMAP/SMTP' or anything else)
	 */
	public function id(): string {

		return 'jmapc';

	}

	/**
	 * The localized human frendly name of this provider
	 *
	 * @since 2024.06.25
	 *
	 * @return string				label/name of this provider (e.g. Plain Old IMAP/SMTP)
	 */
	public function label(): string {

		return 'JMAP Connector';

	}

	/**
	 * Determain if any services are configured for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @return bool 				true if any services are configure for the user
	 */
	public function hasServices(string $uid): bool {

		return (count($this->listServices($uid)) > 0);

	}

	/**
	 * retrieve collection of services for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @return array<string,IService>		collection of service objects
	 */
	public function listServices(string $uid): array {

		try {
			// retrieve service(s) details from data store
			$accounts = $this->ServicesService->fetchByUserId($uid);
		} catch (\Throwable $th) {
			return [];
		}
		// construct temporary collection
		$services = [];
		// add services to collection
		foreach ($accounts as $entry) {
			// add service to collection
			$services[$entry['id']] = $this->instanceService($uid, $entry);
		}
		// return list of services for user
		return $services;

	}

	/**
	 * Retrieve a service with a specific id
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid				user id
	 * @param string $id				service id
	 *
	 * @return IService|null			returns service object or null if non found
	 */
	public function findServiceById(string $uid, string $id): IService | null {

		// evaluate if id is a number
		if (is_numeric($id)) {
			try {
				// retrieve service details from data store
				$account = $this->ServicesService->fetchByUserIdAndServiceId($uid, (int) $id);
			} catch(\Throwable $th) {
				return null;
			}
		}
		// evaliate if service details where found
		if ($account instanceof Account) {
			// return mail service instance
			return $this->instanceService($uid, $account);
		}

		return null;
		
	}

	/**
	 * Retrieve a service for a specific mail address
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid				user id
	 * @param string $address			mail address (e.g. test@example.com)
	 *
	 * @return IService					returns service object or null if non found
	 */
	public function findServiceByAddress(string $uid, string $address): IService | null {

		try {
			// retrieve service details from data store
			$accounts = $this->ServicesService->fetchByUserIdAndAddress($uid, $address);
		} catch(\Throwable $th) {
			return null;
		}
		// evaliate if service details where found
		if (is_array($accounts) && count($accounts) > 0 && is_array($accounts[0])) {
			// return mail service instance
			return $this->instanceService($uid, $accounts[0]);
		}

		return null;

	}

	protected function instanceService(string $uid, array $entry): Service {
		// extract values
		$id = (string) $entry['id'];
		$label = $entry['label'];
		$address = new MailAddress($entry['address_primary'], '');
		$location = new ServiceLocation(
			$entry['location_host'],
			$entry['location_path'],
			$entry['location_port'],
			$entry['location_protocol']
		);

		if ($entry['auth'] == 'OA') {
			$identity = new ServiceIdentityOAuth(
			);
		} else {
			$identity = new ServiceIdentityBAuth(
				$entry['bauth_id'],
				$entry['bauth_secret'],
			);
		}
		return new Service($this->container, $uid, $id, $label, $address, $identity, $location);
	}

	/**
	 * create a service configuration for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid			user id of user to configure service for
	 * @param IService $service 	service configuration object
	 *
	 * @return string				id of created service
	 */
	public function createService(string $uid, IService $service): string {

		return '';

	}

	/**
	 * modify a service configuration for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid			user id of user to configure service for
	 * @param IService $service 	service configuration object
	 *
	 * @return string				id of modifided service
	 */
	public function modifyService(string $uid, IService $service): string {

		return '';

	}

	/**
	 * delete a service configuration for a specific user
	 *
	 * @since 2024.06.25
	 *
	 * @param string $uid			user id of user to delete service for
	 * @param IService $service 	service configuration object
	 *
	 * @return bool					status of delete action
	 */
	public function deleteService(string $uid, IService $service): bool {

		return false;

	}

}
