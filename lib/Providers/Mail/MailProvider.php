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
namespace OCA\JMAPC\Providers\Mail;

use OCP\Mail\Provider\IProvider;
use OCP\Mail\Provider\IService;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Service\AccountService;

class MailProvider implements IProvider {

	private $_ServicesService;
	private ?array $_ServiceCollection = null;

	public function __construct(AccountService $AccountService) {
		
		$this->_ServicesService = $AccountService;

	}

	/**
	 * An arbitrary unique text string identifying this provider
	 * @since 29.0.0
	 */
	public function id(): string {

		return 'JMAPC';

	}

	/**
	 * The localized human frendly name of this provider
	 * @since 29.0.0
	 */
	public function name(): string {

		return 'JMAP Connector';

	}

	/**
	 * 
	 * @since 29.0.0
	 */
	public function hasServices(string $uid): bool {

		return (count($this->listServices($uid)) > 0);

	}

	/**
	 * 
	 * @since 29.0.0
	 */
	public function listServices(string $uid): array {

		// evaluate if collection of services is null
		if (!is_array($this->_ServiceCollection)) {
			// define services collection
			$this->_ServiceCollection = [];
			// retrieve list of services from data store
			$services = $this->_ServicesService->fetchAny($uid);
			// add services to collection
			foreach ($services as $entry) {
				// extract values
				$id = (string) $entry['id'];
				$label = $entry['label'];
				$email = $entry['email'];
				if ($entry['auth'] == 'BA') {
					$identity = new MailServiceIdentityBAuth($entry['bauth_identity'], $entry['bauth_secret']);
				} elseif ($entry['auth'] == 'OA') {
					$identity = new MailServiceIdentityOAuth($entry['oauth_access'], $entry['oauth_expiry'], $entry['oauth_refresh']);
				}
				$location = new MailServiceLocation($entry['host'], $entry['path'], $entry['port']);
				// add service to collection
				$this->_ServiceCollection[] = new MailService($id, $label, $identity, $location);
			}
		}
		// return list of services for user
		return $this->_ServiceCollection;

	}

	/**
	 * 
	 * @since 29.0.0
	 */
	public function createService(string $uid, IService $service): string {

		// add service to collection
		$this->_ServiceCollection[$service->id()] = $service;
		// create service in datastore and return id
		return $this->_ServicesService->create($uid, $service);

	}

	/**
	 * 
	 * @since 29.0.0
	 */
	public function modifyService(string $uid, IService $service): string {

		// update service to collection
		$this->_ServiceCollection[$service->id()] = $service;
		// update service in data store and return id
		return $this->AccountService->update($uid, $sid, $service);

	}

	/**
	 * 
	 * @since 29.0.0
	 */
	public function deleteService(string $uid, IService $service): bool {

		// delete service from collection
		unset($this->_ServiceCollection[$service->id()]);
		// delete service from data and return confirmation
		return $this->AccountService->delete($uid, $service);

	}
	
}
