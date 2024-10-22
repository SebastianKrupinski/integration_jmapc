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

namespace OCA\JMAPC\Service;

use DateTime;
use Exception;
use Throwable;
use Psr\Log\LoggerInterface;
use JmapClient\Client as JmapClient;

use OCP\Notification\IManager as INotificationManager;
use OCP\BackgroundJob\IJobList;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Service\ConfigurationService;
use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Service\HarmonizationThreadService;
use OCA\JMAPC\Service\Local\LocalService;
use OCA\JMAPC\Service\Local\LocalContactsService;
use OCA\JMAPC\Service\Local\LocalEventsService;
use OCA\JMAPC\Service\Local\LocalTasksService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Service\Remote\RemoteContactsService;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
/*
use OCA\JMAPC\Service\Remote\RemoteTasksService;
*/
use OCA\JMAPC\Service\Remote\RemoteCommonService;
/*
use OCA\JMAPC\Tasks\HarmonizationLauncher;
*/


class CoreService {

	public function __construct (
		private LoggerInterface $logger,
		private IJobList $TaskService,
		private INotificationManager $notificationManager,
		private ConfigurationService $ConfigurationService,
		private ServicesService $ServicesService,
		private HarmonizationThreadService $HarmonizationThreadService,
		private LocalService $localService,
		private LocalContactsService $localContactsService,
		private LocalEventsService $localEventsService,
		private LocalTasksService $localTasksService,
		private RemoteService $remoteService,
		private RemoteContactsService $remoteContactsService,
		private RemoteEventsService $remoteEventsService,
		/*
		private RemoteTasksService $remoteTasksService,
		*/
		private RemoteCommonService $remoteCommonService,
	) {}

	/**
	 * locates connection point using users login details 
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid					user id
	 * @param string $account_bauth_id		account username
	 * @param string $account_bauth_secret	account secret
	 * 
	 * @return object
	 */
	public function locateAccount(string $account_bauth_id, string $account_bauth_secret): ?object {

		// construct locator
		$locator = new \OCA\JMAPC\Utile\Eas\EasLocator($account_bauth_id, $account_bauth_secret);
		// find configuration
		$result = $locator->locate();

		if ($result > 0) {
			$data = $locator->discovered;

			$o = new \stdClass();
			$o->UserDisplayName = $data['User']['DisplayName'];
			$o->UserEMailAddress = $data['User']['EMailAddress'];
			$o->UserSMTPAddress = $data['User']['AutoDiscoverSMTPAddress'];
			$o->UserSecret = $account_bauth_secret;

			foreach ($data['Account']['Protocol'] as $entry) {
				// evaluate if type is EXCH
				if ($entry['Type'] == 'EXCH') {
					$o->EXCH = new \stdClass();
					$o->EXCH->Server = $entry['Server'];
					$o->EXCH->AD = $entry['AD'];
					$o->EXCH->ASUrl = $entry['ASUrl'];
					$o->EXCH->EwsUrl = $entry['EwsUrl'];
					$o->EXCH->OOFUrl = $entry['OOFUrl'];
				}
				// evaluate if type is IMAP
				elseif ($entry['Type'] == 'IMAP') {
					if ($entry['SSL'] == 'on') {
						$o->IMAPS = new \stdClass();
						$o->IMAPS->Server = $entry['Server'];
						$o->IMAPS->Port = (int) $entry['Port'];
						$o->IMAPS->AuthMode = 'ssl';
						$o->IMAPS->AuthId = $entry['LoginName'];
					} else {
						$o->IMAP = new \stdClass();
						$o->IMAP->Server = $entry['Server'];
						$o->IMAP->Port = (int) $entry['Port'];
						$o->IMAP->AuthMode = 'tls';
						$o->IMAP->AuthId = $entry['LoginName'];
					}
				}
				// evaluate if type is SMTP
				elseif ($entry['Type'] == 'SMTP') {
					if ($entry['SSL'] == 'on') {
						$o->SMTPS = new \stdClass();
						$o->SMTPS->Server = $entry['Server'];
						$o->SMTPS->Port = (int) $entry['Port'];
						$o->SMTPS->AuthMode = 'ssl';
						$o->SMTPS->AuthId = $entry['LoginName'];
					}
					else {
						$o->SMTP = new \stdClass();
						$o->SMTP->Server = $entry['Server'];
						$o->SMTP->Port = (int) $entry['Port'];
						$o->SMTP->AuthMode = 'tls';
						$o->SMTP->AuthId = $entry['LoginName'];
					}
				}
			}

			return $o;

		} else {
			return null;
		}

	}

	/**
	 * connects to account, verifies details, then create service
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid					user id
	 * @param string $account_bauth_id		account username
	 * @param string $account_bauth_secret	account secret
	 * @param string $account_server		FQDN or IP
	 * @param array $flags
	 * 
	 * @return bool
	 */
	public function connectAccount(string $uid, array $service, array $flags = []): bool {

		// extract values
		$service_id = $service['id'] ?? null;
		$service_label = $service['label'] ?? 'Unknown';
		$service_auth = $service['auth'] ?? 'BA';
		$service_bauth_id = $service['bauth_id'] ?? null;
		$service_bauth_secret = $service['bauth_secret'] ?? null;
		$service_oauth_id = $service['oauth_id'] ?? null;
		$service_oauth_access_token = $service['oauth_access_token'] ?? null;
		$service_location_protocol = $service['location_protocol'] ?? 'https://';
		$service_location_host = $service['location_host'] ?? null;
		$service_location_port = $service['location_port'] ?? '443';
		$service_location_path = $service['location_path'] ?? '/';
		$service_primary_address = $service_auth === 'BA' ? $service_bauth_id : $service_oauth_id;
		
		// validate server
		if (!\OCA\JMAPC\Utile\Validator::host($service_location_host)) {
			return false;
		}

		if ($service_auth === 'BA') {
			// validate id
			if (!\OCA\JMAPC\Utile\Validator::username($service_bauth_id)) {
				return false;
			}
			// validate secret
			if (empty($service_bauth_secret)) {
				return false;
			}
		}
		elseif ($service_auth === 'OA') {
			// validate id
			if (!\OCA\JMAPC\Utile\Validator::username($service_oauth_id)) {
				return false;
			}
			// validate secret
			if (empty($service_oauth_access_token)) {
				return false;
			}
		}
		else {
			return false;
		}

		// construct remote data store client
		$remoteStore = $this->RemoteService->initializeStoreFromCollection([
			'auth' => $service_auth,
			'bauth_id' => $service_bauth_id,
			'bauth_secret' => $service_bauth_secret,
			'oauth_id' => $service_oauth_id,
			'oauth_access_token' => $service_oauth_access_token,
			'location_protocol' => $service_location_protocol,
			'location_host' => $service_location_host,
			'location_port' => $service_location_port,
			'location_path' => $service_location_path,
			'address_primary' => $service_primary_address,
		]);
	
		// connect client
		$remoteStore->connect();

		// determine if connection was established
		if ($remoteStore->sessionStatus() === false) {
			return false;
		}

		// TODO: retrieve capabilities

		// deposit service to datastore
		$service = [
			'enabled' => 1,
			'connected' => 1,
			'label'=> $service_label,
			'auth' => $service_auth,
			'bauth_id' => $service_bauth_id,
			'bauth_secret' => $service_bauth_secret,
			'oauth_id' => $service_oauth_id,
			'oauth_access_token' => $service_oauth_access_token,
			'location_protocol' => $service_location_protocol,
			'location_host' => $service_location_host,
			'location_port' => $service_location_port,
			'location_path' => $service_location_path,
			'address_primary' => $service_primary_address,
		];

		if (isset($service_id)) {
			$service['id'] = $service_id;
		}

		$this->ServicesService->deposit($uid, $service);

		// register harmonization task
		//$this->TaskService->add(\OCA\JMAPC\Tasks\HarmonizationLauncher::class, ['uid' => $uid]);

		return true;

	}

	/**
	 * Removes all users settings, etc for specific user
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * @param int $sid			service id
	 * 
	 * @return void
	 */
	public function disconnectAccount(string $uid, string $sid): void {
		
		// deregister task
		$this->TaskService->remove(\OCA\JMAPC\Tasks\HarmonizationLauncher::class, ['uid' => $uid]);
		// terminate harmonization thread
		$this->HarmonizationThreadService->terminate($uid);
		// initialize contacts data store
		$DataStore = \OC::$server->get(\OCA\JMAPC\Store\ContactStore::class);
		// delete local entities
		$DataStore->entityDeleteByUser($uid);
		// delete local collection
		$DataStore->collectionDeletesByUser($uid);
		// initialize events data store
		$DataStore = \OC::$server->get(\OCA\JMAPC\Store\EventStore::class);
		// delete local entities
		$DataStore->entityDeleteByUser($uid);
		// delete local collection
		$DataStore->collectionDeletesByUser($uid);
		// initialize tasks data store
		$DataStore = \OC::$server->get(\OCA\JMAPC\Store\TaskStore::class);
		// delete local entities
		$DataStore->entityDeleteByUser($uid);
		// delete local collection
		$DataStore->collectionDeletesByUser($uid);
		// delete configuration
		$this->ConfigurationService->destroyUser($uid);

	}

	/**
	 * retrieves remote collections for all modules
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * @param int $sid			service id
	 * 
	 * @return array of remote collection(s) and attributes
	 */
	public function remoteCollectionsFetch(string $uid, int $sid): array {

		// construct response object
		$data = ['ContactCollections' => [], 'EventCollections' => [], 'TaskCollections' => []];
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user if the service owner
		if ($service['uid'] !== $uid) {
			return $data;
		}
		// create remote store client
		$remoteStore = $this->remoteService->initializeStoreFromCollection($service);
		// retrieve collections for contacts module
		$this->remoteContactsService->initialize($remoteStore);
		try {
			$collections = $this->remoteContactsService->collectionList();
			$data['ContactCollections'] = array_map(function($collection) {
				return ['id' => $collection->Id, 'name' => 'Personal - ' . $collection->Name];
			}, $collections);
		} catch (JmapUnknownMethod $e) {
			// AddressBook name space is not supported fail silently
		}
		// if AddressBook name space is not supported see if Contacts name space works
		if (count($data['ContactCollections']) === 0) {
			try {
				$list = $this->remoteContactsService->entityList('', null, null, 'B');
				$data['ContactCollections'][] = ['id' => '', 'name' => 'Personal - Contacts', 'count' => $list['total']];
			} catch (\Throwable $e) {
				// ContactCard name space is not supported fail silently
			}
			
		}
		// retrieve collections for events module
		$this->remoteEventsService->initialize($remoteStore);
		try {
			$collections = $this->remoteEventsService->collectionList();
			$data['EventCollections'] = array_map(function($collection) {
				return ['id' => $collection->Id, 'name' => 'Personal - ' . $collection->Name];
			}, $collections);
		} catch (JmapUnknownMethod $e) {
			// AddressBook name space is not supported fail silently
		}
		// if AddressBook name space is not supported see if Contacts name space works
		if (count($data['EventCollections']) === 0) {
			try {
				$list = $this->remoteEventsService->entityList('', null, null, 'B');
				$data['EventCollections'][] = ['id' => '', 'name' => 'Personal - Calendar', 'count' => $list['total']];
			} catch (\Throwable $e) {
				// ContactCard name space is not supported fail silently
			}
			
		}
		
		// return response
		return $data;

	}

	/**
	 * retrieves local collections for all modules
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * @param int $sid			service id
	 * 
	 * @return array of collection correlation(s) and attributes
	 */
	public function localCollectionsFetch(string $uid, int $sid): array {

		// construct response object
		$data = ['ContactCollections' => [], 'EventCollections' => [], 'TaskCollections' => []];
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user if the service owner
		if ($service['uid'] !== $uid) {
			return $data;
		}
		// retrieve local collections
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			$localStore = $this->localService->initializeContactStore();
			$data['ContactCollections'] = $localStore->collectionListByService($sid);
		}
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			$localStore = $this->localService->initializeEventStore();
			$data['EventCollections'] = $localStore->collectionListByService($sid);
		}
		if ($this->ConfigurationService->isTasksAppAvailable()) {
			$localStore = $this->localService->initializeTaskStore();
			$data['TaskCollections'] = $localStore->collectionListByService($sid);
		}
		// return response
		return $data;

	}

	/**
	 * Deposit collection correlations for all modules
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		user id
	 * @param int $sid			service id
	 * @param array $cc		contacts collection(s) correlations
	 * @param array $ec		events collection(s) correlations
	 * @param array $tc		tasks collection(s) correlations
	 * 
	 * @return array of collection correlation(s) and attributes
	 */
	public function depositCorrelations(string $uid, int $sid, array $cc, array $ec, array $tc): void {
		
		// terminate harmonization thread, in case the user changed any correlations
		//$this->HarmonizationThreadService->terminate($uid);
		// deposit contacts correlations
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			// initialize data store
			$DataStore = \OC::$server->get(\OCA\JMAPC\Store\ContactStore::class);
			// process entries
			foreach ($cc as $entry) {
				if (isset($entry['enabled'])) {
					try {
						switch ((bool) $entry['enabled']) {
							case false:
								if (!empty($entry['id'])) {
									// retrieve correlation entry
									$cr = $this->CorrelationsService->fetch($entry['id']);
									// evaluate if user id matches
									if ($uid == $cr->getuid()) {
										// delete local entities
										$DataStore->entityDeleteByCollection($uid, $cr->getloid());
										// delete local collection
										$DataStore->collectionDelete($cr->getloid());
										// delete correlations
										$this->CorrelationsService->deleteByCollectionId($cr->getuid(), $cr->getloid(), $cr->getroid());
										$this->CorrelationsService->delete($cr);
									}
								}
								break;
							case true:
								if (empty($entry['id'])) {
									// create local collection
									$cl = [];
									$cl['uid'] = $uid; // user id
									$cl['sid'] = $sid; // service id
									$cl['uuid'] = \OCA\JMAPC\Utile\UUID::v4(); // universal id
									$cl['label'] = 'JMAP: ' . $entry['label']; // collection Label
									$cl['color'] = $entry['color']; // collection Color
									$cl['rcid'] = $entry['roid']; // remote collection id
									$cl['rcsn'] = ''; // remote collection signature
									$cid = $DataStore->collectionCreate($cl);
									// create correlation
									$cr = new \OCA\JMAPC\Store\Correlation();
									$cr->settype('CC'); // Correlation Type
									$cr->setuid($uid); // User ID
									$cr->setsid($sid); // Service ID
									$cr->setloid($cid); // Local Collection ID
									$cr->setroid($entry['roid']); // Remote Collection ID
									$this->CorrelationsService->create($cr);
								}
								break;
						}
					}
					catch (Exception $e) {
						
					}
				}
			}
		}
		// deposit events correlations
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			// initialize data store
			$DataStore = \OC::$server->get(\OCA\JMAPC\Store\EventStore::class);
			// process entries
			foreach ($ec as $entry) {
				if (isset($entry['enabled'])) {
					try {
						switch ((bool) $entry['enabled']) {
							case false:
								if (!empty($entry['id'])) {
									// retrieve correlation entry
									$cr = $this->CorrelationsService->fetch($entry['id']);
									// evaluate if user id matches
									if ($uid == $cr->getuid()) {
										// delete local entities
										$DataStore->entityDeleteByCollection($uid, $cr->getloid());
										// delete local collection
										$DataStore->collectionDelete($cr->getloid());
										// delete correlations
										$this->CorrelationsService->deleteByCollectionId($cr->getuid(), $cr->getloid(), $cr->getroid());
										$this->CorrelationsService->delete($cr);
									}
								}
								break;
							case true:
								if (empty($entry['id'])) {
									// create local collection
									$cl = [];
									$cl['uid'] = $uid; // user id
									$cl['sid'] = $sid; // service id
									$cl['uuid'] = \OCA\JMAPC\Utile\UUID::v4(); // universal id
									$cl['label'] = 'JMAP: ' . $entry['label']; // collection Label
									$cl['color'] = $entry['color']; // collection Color
									$cl['rcid'] = $entry['roid']; // remote collection id
									$cl['rcsn'] = ''; // remote collection signature
									$cid = $DataStore->collectionCreate($cl);
									// create correlation
									$cr = new \OCA\JMAPC\Store\Correlation();
									$cr->settype('EC'); // Correlation Type
									$cr->setuid($uid); // User ID
									$cr->setsid($sid); // Service ID
									$cr->setloid($cid); // Local Collection ID
									$cr->setroid($entry['roid']); // Remote Collection ID
									$this->CorrelationsService->create($cr);
								}
								break;
						}
					}
					catch (Exception $e) {
						
					}
				}
			}
		}
		// deposit tasks correlations
		if ($this->ConfigurationService->isTasksAppAvailable()) {
			// initialize data store
			$DataStore = \OC::$server->get(\OCA\JMAPC\Store\TaskStore::class);
			// process entries
			foreach ($tc as $entry) {
				if (isset($entry['enabled'])) {
					try {
						switch ((bool) $entry['enabled']) {
							case false:
								if (!empty($entry['id'])) {
									// retrieve correlation entry
									$cr = $this->CorrelationsService->fetch($entry['id']);
									// evaluate if user id matches
									if ($uid == $cr->getuid()) {
										// delete local entities
										$DataStore->entityDeleteByCollection($uid, $cr->getloid());
										// delete local collection
										$DataStore->collectionDelete($cr->getloid());
										// delete correlations
										$this->CorrelationsService->deleteByCollectionId($cr->getuid(), $cr->getloid(), $cr->getroid());
										$this->CorrelationsService->delete($cr);
									}
								}
								break;
							case true:
								if (empty($entry['id'])) {
									// create local collection
									$cl = [];
									$cl['uid'] = $uid; // user id
									$cl['sid'] = $sid; // service id
									$cl['uuid'] = \OCA\JMAPC\Utile\UUID::v4(); // universal id
									$cl['label'] = 'JMAP: ' . $entry['label']; // collection Label
									$cl['color'] = $entry['color']; // collection Color
									$cl['rcid'] = $entry['roid']; // remote collection id
									$cl['rcsn'] = ''; // remote collection signature
									$cid = $DataStore->collectionCreate($cl);
									// create correlation
									$cr = new \OCA\JMAPC\Store\Correlation();
									$cr->settype('TC'); // Correlation Type
									$cr->setuid($uid); // User ID
									$cr->setsid($sid); // Service ID
									$cr->setloid($cid); // Local Collection ID
									$cr->setroid($entry['roid']); // Remote Collection ID
									$this->CorrelationsService->create($cr);
								}
								break;
						}
					}
					catch (Exception $e) {
						
					}
				}
			}
		}
	}
	
	/**
	 * publish user notification
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		nextcloud user id
	 * @param array $subject	notification type
	 * @param array $params		notification parameters to pass
	 * 
	 * @return array of collection correlation(s) and attributes
	 */
	public function publishNotice(string $uid, string $subject, array $params): void {
		// construct notification object
		$notification = $this->notificationManager->createNotification();
		// assign attributes
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new DateTime())
			->setObject('eas', 'eas')
			->setSubject($subject, $params);
		// submit notification
		$this->notificationManager->notify($notification);
	}

}
