<?php
//declare(strict_types=1);

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

use JmapClient\Client as JmapClient;
use JmapClient\Authentication\Basic;
use JmapClient\Authentication\Bearer;
use JmapClient\Authentication\JsonBasic;
use OCA\JMAPC\Providers\IServiceIdentity;
use OCA\JMAPC\Providers\IServiceLocation;

class RemoteService {

	//static string $clientTransportAgent = 'NextcloudJMAP/1.0 (1.0; x64)';
	static string $clientTransportAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0';

	public function __construct () {

	}

	/**
	 * Initialize remote data store client
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param array $service
	 * 
	 * @return JmapClient
	 */
	public static function initializeStoreFromCollection(array $service): JmapClient {
		
		// construct client and set defaults
		$client = new JmapClient();
		$client->setTransportAgent(self::$clientTransportAgent);
		// set location parameters from service
		$client->configureTransportMode($service['location_protocol']);
		$client->setHost($service['location_host'] . ':' . $service['location_port']);
		if (!empty($service['location_path'])) {
			$client->setDiscoveryPath($service['location_path']);
		}
		if (isset($service['location_security'])) {
			$client->configureTransportVerification((bool)$service['location_security']);
		}
		// set authentication parameters from service
		if ($service['auth'] == 'OA') {
			$client->setAuthentication(new Bearer($service['oauth_id'], $service['oauth_access_token'], 0));
		}
		if ($service['auth'] == 'BA') {
			$client->setAuthentication(new Basic($service['bauth_id'], $service['bauth_secret']));
		}
		if ($service['auth'] == 'JB') {
			$client->setAuthentication(new JsonBasic($service['bauth_id'], $service['bauth_secret']));
		}
		//
		if ((bool)$service['debug']) {
			$client->configureTransportLogState(true);
			$client->configureTransportLogLocation(
				'/tmp/' . $service['location_host'] . '-' . $service['address_primary']
			);
		}
		// return configured client
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

}
