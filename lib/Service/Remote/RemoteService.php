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

namespace OCA\JMAPC\Service\Remote;

use JmapClient\Authentication\Basic;
use JmapClient\Authentication\Bearer;
use JmapClient\Authentication\JsonBasic;
use JmapClient\Client as JmapClient;
use OCA\JMAPC\Providers\IServiceIdentity;
use OCA\JMAPC\Providers\IServiceLocation;
use OCA\JMAPC\Service\Remote\FM\RemoteContactsServiceFM;
use OCA\JMAPC\Service\Remote\FM\RemoteEventsServiceFM;
use OCA\JMAPC\Store\Local\ServiceEntity;

class RemoteService {

	//static string $clientTransportAgent = 'NextcloudJMAP/1.0 (1.0; x64)';
	public static string $clientTransportAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0';

	public function __construct() {

	}

	/**
	 * Initialize remote data store client
	 *
	 * @since Release 1.0.0
	 *
	 * @param ServiceEntity $service
	 *
	 * @return JmapClient
	 */
	public static function initializeStoreFromEntity(ServiceEntity $service): JmapClient {
		
		// defaults
		$client = new JmapClient();
		$client->setTransportAgent(self::$clientTransportAgent);
		// location
		$client->configureTransportMode($service->getLocationProtocol());
		$client->setHost($service->getLocationHost() . ':' . $service->getLocationPort());
		if (!empty($service->getLocationPath())) {
			$client->setDiscoveryPath($service->getLocationPath());
		}
		$client->configureTransportVerification((bool)$service->getLocationSecurity());
		// authentication
		if ($service->getAuth() == 'OA') {
			$client->setAuthentication(new Bearer($service->getOauthId(), $service->getOauthAccessToken(), 0));
		}
		if ($service->getAuth() == 'BA') {
			$client->setAuthentication(new Basic($service->getBauthId(), $service->getBauthSecret()));
		}
		if ($service->getAuth() == 'JB') {
			$client->setAuthentication(new JsonBasic(
				$service->getBauthId(),
				$service->getBauthSecret(),
				$service->getLocationProtocol() . $service->getLocationHost() . ':' . $service->getLocationPort() . '/auth/sessions',
				true
			));
			$client->configureTransportCookieJar(sys_get_temp_dir() . '/' . $service->getLocationHost() . '-' . $service->getAddressPrimary() . '.jmapc');
		}
		// debugging
		if ($service->getDebug()) {
			$client->configureTransportLogState(true);
			$client->configureTransportLogLocation(
				sys_get_temp_dir() . '/' . $service->getLocationHost() . '-' . $service->getAddressPrimary() . '.log'
			);
		}
		// return
		return $client;

	}

	/**
	 * Initialize remote data store client
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid
	 *
	 * @return Client
	 */
	public static function initializeStoreFromService(IServiceLocation $location, IServiceIdentity $identity): JmapClient {

		$client = new JmapClient();
		$client->configureTransportMode($location->getScheme());
		$client->setHost($location->getHost() . ':' . $location->getPort());

		if ($identity->type() == 'OA') {
			$client->setAuthentication(new Bearer($identity->getAccessId(), $identity->getAccessToken(), $identity->getAccessExpiry()));
		}

		if ($identity->type() == 'BA') {
			$client->setAuthentication(new Basic($identity->getIdentity(), $identity->getSecret()));
		}

		return $client;
	}

	/**
	 * Destroys remote data store client (Jmap Client)
	 *
	 * @since Release 1.0.0
	 *
	 * @param JmapClient $Client
	 *
	 * @return void
	 */
	public static function storeDestroy(JmapClient $Client): void {
		
		// destroy remote data store client
		$Client = null;

	}

	/**
	 * Appropriate Contacts Service for Connection
	 *
	 * @since Release 1.0.0
	 *
	 * @param JmapClient $Client
	 *
	 * @return void
	 */
	public static function contactsService(JmapClient $Client): RemoteContactsService {

		// evaluate if client is connected
		if (!$Client->sessionStatus()) {
			$Client->connect();
		}
		// determine capabilities
		if ($Client->sessionCapable('https://www.fastmail.com/dev/contacts', false)) {
			return new RemoteContactsServiceFM();
		}

		return new RemoteContactsService();

	}

	/**
	 * Appropriate Events Service for Connection
	 *
	 * @since Release 1.0.0
	 *
	 * @param JmapClient $Client
	 *
	 * @return void
	 */
	public static function eventsService(JmapClient $Client): RemoteEventsService {

		// evaluate if client is connected
		if (!$Client->sessionStatus()) {
			$Client->connect();
		}
		// determine capabilities
		if ($Client->sessionCapable('https://www.fastmail.com/dev/calendars', false)) {
			return new RemoteEventsServiceFM();
		}

		return new RemoteEventsService();

	}

	/**
	 * Appropriate Tasks Service for Connection
	 *
	 * @since Release 1.0.0
	 *
	 * @param JmapClient $Client
	 *
	 * @return void
	 */
	public static function tasksService(JmapClient $Client): RemoteTasksService {

		return new RemoteTasksService();

	}

}
