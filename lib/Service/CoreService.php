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

namespace OCA\JMAPC\Service;

use DateTime;
use OCA\JMAPC\AppInfo\Application;

use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Service\Local\LocalService;

use OCA\JMAPC\Service\Remote\RemoteCommonService;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use OCP\BackgroundJob\IJobList;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/*
use OCA\JMAPC\Tasks\HarmonizationLauncher;
*/


class CoreService {

	public function __construct(
		private LoggerInterface $logger,
		private IJobList $TaskService,
		private INotificationManager $notificationManager,
		private ConfigurationService $ConfigurationService,
		private ServicesService $ServicesService,
		private HarmonizationThreadService $HarmonizationThreadService,
		private LocalService $localService,
		private RemoteService $remoteService,
		private RemoteCommonService $remoteCommonService,
	) {
	}

	/**
	 * locates connection point using users login details
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param string $account_bauth_id account username
	 * @param string $account_bauth_secret account secret
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
						$o->IMAPS->Port = (int)$entry['Port'];
						$o->IMAPS->AuthMode = 'ssl';
						$o->IMAPS->AuthId = $entry['LoginName'];
					} else {
						$o->IMAP = new \stdClass();
						$o->IMAP->Server = $entry['Server'];
						$o->IMAP->Port = (int)$entry['Port'];
						$o->IMAP->AuthMode = 'tls';
						$o->IMAP->AuthId = $entry['LoginName'];
					}
				}
				// evaluate if type is SMTP
				elseif ($entry['Type'] == 'SMTP') {
					if ($entry['SSL'] == 'on') {
						$o->SMTPS = new \stdClass();
						$o->SMTPS->Server = $entry['Server'];
						$o->SMTPS->Port = (int)$entry['Port'];
						$o->SMTPS->AuthMode = 'ssl';
						$o->SMTPS->AuthId = $entry['LoginName'];
					} else {
						$o->SMTP = new \stdClass();
						$o->SMTP->Server = $entry['Server'];
						$o->SMTP->Port = (int)$entry['Port'];
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
	 * @param string $uid user id
	 * @param array $service service connection data
	 * @param array $flags
	 *
	 * @return bool
	 */
	public function connectAccount(string $uid, array $service, array $flags = []): bool {

		// validate server
		if (!\OCA\JMAPC\Utile\Validator::host($service['location_host'])) {
			return false;
		}

		if ($service['auth'] === 'BA' || $service['auth'] === 'JA') {
			// validate id
			if (!\OCA\JMAPC\Utile\Validator::username($service['bauth_id'])) {
				return false;
			}
			// validate secret
			if (empty($service['bauth_secret'])) {
				return false;
			}
		} elseif ($service['auth'] === 'OA') {
			// validate id
			if (!\OCA\JMAPC\Utile\Validator::username($service['oauth_id'])) {
				return false;
			}
			// validate secret
			if (empty($service['oauth_access_token'])) {
				return false;
			}
		} else {
			return false;
		}

		// construct service entity
		$service = new ServiceEntity();
		if (isset($service['id'])) {
			$service->setId($service['id']);
		}
		$service->setLabel($service['label'] ?? 'Unknown');
		$service->setLocationProtocol($service['location_protocol'] ?? 'https://');
		$service->setLocationHost($service['location_host']);
		$service->setLocationPort($service['location_port'] ?? 443);
		$service->setLocationPath($service['location_path'] ?? null);
		$service->setLocationSecurity((bool)$service['location_security'] ?? 1);
		$service->setAuth($service['auth']);
		if ($service['auth'] === 'BA' || $service['auth'] === 'JA') {
			$service->setBauthId($service['bauth_id']);
			$service->setBauthSecret($service['bauth_secret']);
			$service->setAddressPrimary($service['bauth_id']);
		} elseif ($service['auth'] === 'OA') {
			$service->setOauthId($service['oauth_id']);
			$service->setOauthAccessToken($service['oauth_access_token']);
			$service->setAddressPrimary($service['oauth_id']);
		}
		
		// construct remote data store client
		$remoteStore = $this->remoteService->initializeStoreFromEntity($service);
	
		// connect client
		$remoteStore->connect();

		// determine if connection was established
		if ($remoteStore->sessionStatus() === false) {
			return false;
		}

		// TODO: retrieve capabilities

		$service->setEnabled(true);
		$service->setConnected(true);

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
	 * @param string $uid user id
	 * @param int $sid service id
	 *
	 * @return void
	 */
	public function disconnectAccount(string $uid, int $sid): void {

		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user if the service owner
		if ($service->getUid() !== $uid) {
			return;
		}
		// deregister task
		$this->TaskService->remove(\OCA\JMAPC\Tasks\HarmonizationLauncher::class, ['uid' => $uid, 'sid' => $sid]);
		// terminate harmonization thread
		$this->HarmonizationThreadService->terminate($uid);
		// initialize contacts data store
		$localStore = $this->localService->initializeContactStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// initialize events data store
		$localStore = $this->localService->initializeEventStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// initialize tasks data store
		$localStore = $this->localService->initializeTaskStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// delete service
		$this->ServicesService->delete($uid, $service);

	}

	/**
	 * retrieves remote collections for all modules
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 *
	 * @return array of remote collection(s) and attributes
	 */
	public function remoteCollectionsFetch(string $uid, int $sid): array {

		// construct response object
		$data = ['ContactCollections' => [], 'EventCollections' => [], 'TaskCollections' => []];
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user is the service owner
		if ($service->getUid() !== $uid) {
			return $data;
		}
		// create remote store client
		$remoteStore = $this->remoteService->initializeStoreFromEntity($service);
		// retrieve collections for contacts module
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			$remoteContactsService = $this->remoteService->contactsService($remoteStore);
			$remoteContactsService->initialize($remoteStore);
			try {
				$collections = $remoteContactsService->collectionList();
				$data['ContactCollections'] = array_map(function ($collection) {
					return ['id' => $collection->Id, 'label' => 'Personal - ' . $collection->Label];
				}, $collections);
			} catch (JmapUnknownMethod $e) {
				// AddressBook name space is not supported fail silently
			}
			// if AddressBook name space is not supported see if Contacts name space works
			if (count($data['ContactCollections']) === 0) {
				try {
					$list = $remoteContactsService->entityList('', 'B');
					$data['ContactCollections'][] = ['id' => 'Default', 'label' => 'Personal - Contacts', 'count' => $list['total']];
				} catch (\Throwable $e) {
					// ContactCard name space is not supported fail silently
				}
				
			}
		}
		// retrieve collections for events module
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			$remoteEventsService = $this->remoteService->eventsService($remoteStore);
			$remoteEventsService->initialize($remoteStore);
			try {
				$collections = $remoteEventsService->collectionList();
				$data['EventCollections'] = array_map(function ($collection) {
					return ['id' => $collection->Id, 'label' => 'Personal - ' . $collection->Label];
				}, $collections);
			} catch (JmapUnknownMethod $e) {
				// AddressBook name space is not supported fail silently
			}
			// if AddressBook name space is not supported see if Contacts name space works
			if (count($data['EventCollections']) === 0) {
				try {
					$list = $remoteEventsService->entityList('', null, null, 'B');
					$data['EventCollections'][] = ['id' => 'Default', 'label' => 'Personal - Calendar', 'count' => $list['total']];
				} catch (\Throwable $e) {
					// ContactCard name space is not supported fail silently
				}
				
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
	 * @param string $uid user id
	 * @param int $sid service id
	 *
	 * @return array of collection correlation(s) and attributes
	 */
	public function localCollectionsFetch(string $uid, int $sid): array {

		// construct response object
		$data = ['ContactCollections' => [], 'EventCollections' => [], 'TaskCollections' => []];
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user if the service owner
		if ($service->getUid() !== $uid) {
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
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param array $cc contacts collection(s) correlations
	 * @param array $ec events collection(s) correlations
	 * @param array $tc tasks collection(s) correlations
	 *
	 * @return array of collection correlation(s) and attributes
	 */
	public function localCollectionsDeposit(string $uid, int $sid, array $cc, array $ec, array $tc): void {
		
		// terminate harmonization thread, in case the user changed any correlations
		//$this->HarmonizationThreadService->terminate($uid);
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user is the service owner
		if ($service->getUid() !== $uid) {
			return;
		}
		// deposit contacts correlations
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			// initialize data store
			$localStore = $this->localService->initializeContactStore();
			// process entries
			foreach ($cc as $entry) {
				if (!isset($entry['enabled']) || !is_bool($entry['enabled'])) {
					continue;
				}
				switch ((bool)$entry['enabled']) {
					case false:
						if (is_numeric($entry['id'])) {
							$collection = $localStore->collectionFetch($entry['id']);
							if ($collection->getUid() === $uid) {
								$localStore->collectionDelete($collection);
							}
						}
						break;
					case true:
						if (empty($entry['id'])) {
							// create local collection
							$collection = $localStore->collectionFresh();
							$collection->setUid($uid);
							$collection->setSid($sid);
							$collection->setCcid($entry['ccid']);
							$collection->setUuid(\OCA\JMAPC\Utile\UUID::v4());
							$collection->setLabel('JMAP: ' . ($entry['label'] ?? 'Unknown'));
							$collection->setColor($entry['color'] ?? '#0055aa');
							$collection->setVisible(true);
							$id = $localStore->collectionCreate($collection);
						}
						break;
				}
			}
		}
		// deposit events correlations
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			// initialize data store
			$localStore = $this->localService->initializeEventStore();
			// process entries
			foreach ($ec as $entry) {
				if (!isset($entry['enabled']) || !is_bool($entry['enabled'])) {
					continue;
				}
				switch ((bool)$entry['enabled']) {
					case false:
						if (is_numeric($entry['id'])) {
							$collection = $localStore->collectionFetch($entry['id']);
							if ($collection->getUid() === $uid) {
								$localStore->collectionDelete($collection);
							}
						}
						break;
					case true:
						if (empty($entry['id'])) {
							// create local collection
							$collection = $localStore->collectionFresh();
							$collection->setUid($uid);
							$collection->setSid($sid);
							$collection->setCcid($entry['ccid']);
							$collection->setUuid(\OCA\JMAPC\Utile\UUID::v4());
							$collection->setLabel('JMAP: ' . ($entry['label'] ?? 'Unknown'));
							$collection->setColor($entry['color'] ?? '#0055aa');
							$collection->setVisible(true);
							$id = $localStore->collectionCreate($collection);
						}
						break;
				}
			}
		}
		// deposit tasks correlations
		if ($this->ConfigurationService->isTasksAppAvailable()) {
			// initialize data store
			$localStore = $this->localService->initializeTaskStore();
			// process entries
			foreach ($tc as $entry) {
				if (!isset($entry['enabled']) || !is_bool($entry['enabled'])) {
					continue;
				}
				switch ((bool)$entry['enabled']) {
					case false:
						if (is_numeric($entry['id'])) {
							$collection = $localStore->collectionFetch($entry['id']);
							if ($collection->getUid() === $uid) {
								$localStore->collectionDelete($collection);
							}
						}
						break;
					case true:
						if (empty($entry['id'])) {
							// create local collection
							$collection = $localStore->collectionFresh();
							$collection->setUid($uid);
							$collection->setSid($sid);
							$collection->setCcid($entry['ccid']);
							$collection->setUuid(\OCA\JMAPC\Utile\UUID::v4());
							$collection->setLabel('JMAP: ' . ($entry['label'] ?? 'Unknown'));
							$collection->setColor($entry['color'] ?? '#0055aa');
							$collection->setVisible(true);
							$id = $localStore->collectionCreate($collection);
						}
						break;
				}
			}
		}
	}
	
	/**
	 * publish user notification
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param array $subject notification type
	 * @param array $params notification parameters to pass
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
