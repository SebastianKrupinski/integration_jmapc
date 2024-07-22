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

use JmapClient\Client;
use JmapClient\Authentication\Basic;
use JmapClient\Authentication\Bearer;
use OCA\JMAPC\Providers\IServiceIdentity;
use OCA\JMAPC\Providers\IServiceLocation;

class RemoteClientService {

	public function __construct () {

	}

	/**
	 * Create remote data store client (Jmap Client)
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid
	 * 
	 * @return Client
	 */
	public function createClient(string $uid): Client {

		
	}

	/**
	 * Create remote data store client (Jmap Client)
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid
	 * 
	 * @return Client
	 */
	public static function createClientFromService(IServiceLocation $location, IServiceIdentity $identity): Client {

		//
		$client = new Client();
		$client->configureTransportMode($location->getScheme());
		$client->setHost($location->getHost() . ':' . $location->getPort());

		if ($identity->type() == 'OAUTH') {
			$client->setAuthentication(new Bearer());
		}

		if ($identity->type() == 'BAUTH') {
			$client->setAuthentication(new Basic($identity->getIdentity(), $identity->getSecret()));
		}

		return $client;
	}

	/**
	 * Destroys remote data store client (Jmap Client)
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param Client $Client
	 * 
	 * @return void
	 */
	public static function destroyClient(Client $Client): void {
		
		// destory remote data store client
		$Client = null;

	}

}
