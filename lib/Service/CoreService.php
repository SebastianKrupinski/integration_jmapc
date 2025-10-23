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
use OCA\JMAPC\Objects\AuthenticationTypes;
use OCA\JMAPC\Service\Local\LocalService;
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
		private ServicesTemplateService $ServicesTemplateService,
		private HarmonizationThreadService $HarmonizationThreadService,
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
	public function locateAccount(array $configuration): ?array {

		// determine account and host from identity
		$identity = $configuration['bauth_id'] ?? $configuration['oauth_id'];
		if (strpos($identity, '@') === false) {
			return null;
		}
		[$identityAccount, $identityDomain] = explode('@', $identity);
		// find template for identity domain
		$template = $this->ServicesTemplateService->fetch($identityDomain);
		if (isset($template[0]['connection'])) {
			$settings = json_decode($template[0]['connection'], true, 512, JSON_THROW_ON_ERROR);
			foreach ($settings as $property => $value) {
				$configuration[$property] = $value;
			}
		}
		// find dns service records
		if (empty($configuration['location_host'])) {
			$dns = dns_get_record('_jmap._tcp.' . $identityDomain, DNS_SRV);
			if ($dns[0]['type'] === 'SRV') {
				$dnsTarget = $dns[0]['target'];
				$dnsPort = $dns[0]['port'];
				$configuration['location_host'] = $dnsTarget;
				$configuration['location_port'] = $dnsPort;
			}
		}
		// find template for dns service target
		if ($dnsTarget) {
			$template = $this->ServicesTemplateService->fetch($dnsTarget);
			if (isset($template[0]['connection'])) {
				$settings = json_decode($template[0]['connection'], true, 512, JSON_THROW_ON_ERROR);
				foreach ($settings as $property => $value) {
					$configuration[$property] = $value;
				}
			}
		}

		if (empty($configuration['location_host'])) {
			$configuration['location_host'] = $identityDomain;
			$configuration['location_path'] = '/.well-known/jmap';
		}

		return $configuration;
	}

	/**
	 * connects to account, verifies details, then create service
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param array $configuration service connection data
	 * @param array $flags
	 *
	 * @return bool
	 */
	public function connectAccount(string $uid, array $configuration, array $flags = []): bool {

		// validate service configuration
		if (!empty($configuration['location_host']) && !\OCA\JMAPC\Utile\Validator::host($configuration['location_host'])) {
			return false;
		}

		if ($configuration['auth'] === AuthenticationTypes::Basic->value ||
			$configuration['auth'] === AuthenticationTypes::JsonBasic->value ||
			$configuration['auth'] === AuthenticationTypes::JsonBasicCookie->value
		) {
			// validate id
			//if (!\OCA\JMAPC\Utile\Validator::username($configuration['bauth_id'])) {
			//	return false;
			//}
			// validate secret
			if (empty($configuration['bauth_secret'])) {
				return false;
			}
		} elseif ($configuration['auth'] === AuthenticationTypes::Bearer->value) {
			// validate id
			if (!\OCA\JMAPC\Utile\Validator::username($configuration['oauth_id'])) {
				return false;
			}
			// validate secret
			if (empty($configuration['oauth_access_token'])) {
				return false;
			}
		} else {
			return false;
		}
		// if host was not provided, attempt to locate it
		if (empty($configuration['location_host'])) {
			$configuration = $this->locateAccount($configuration);
		}

		// construct service entity
		$service = new ServiceEntity();
		if (isset($configuration['id'])) {
			unset($configuration['id']);
		}
		$service->setUuid(\OCA\JMAPC\Utile\UUID::v4());
		$service->setLabel($configuration['label'] ?? 'Unknown');
		$service->setLocationProtocol($configuration['location_protocol'] ?? 'https');
		$service->setLocationHost($configuration['location_host']);
		$service->setLocationPort($configuration['location_port'] ?? 443);
		$service->setLocationPath($configuration['location_path'] ?? null);
		$service->setLocationSecurity((bool)$configuration['location_security'] ?? 1);
		$service->setAuth($configuration['auth']);
		if ($configuration['auth'] === AuthenticationTypes::Basic->value ||
			$configuration['auth'] === AuthenticationTypes::JsonBasic->value ||
			$configuration['auth'] === AuthenticationTypes::JsonBasicCookie->value
		) {
			$service->setBauthId($configuration['bauth_id']);
			$service->setBauthSecret($configuration['bauth_secret']);
			$service->setBauthLocation($configuration['bauth_location'] ?? null);
			$service->setAddressPrimary($configuration['bauth_id']);
		}
		if ($configuration['auth'] === AuthenticationTypes::JsonBasicCookie->value) {
			$service->setCauthLocation($configuration['cauth_location'] ?? null);
		}
		if ($configuration['auth'] === AuthenticationTypes::Bearer->value) {
			$service->setOauthId($configuration['oauth_id']);
			$service->setOauthAccessToken($configuration['oauth_access_token']);
			$service->setOauthLocation($configuration['oauth_location'] ?? null);
			$service->setAddressPrimary($configuration['oauth_id']);
		}

		// construct remote data store client
		$remoteStore = RemoteService::freshClient($service);

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
		$this->TaskService->add(\OCA\JMAPC\Tasks\HarmonizationLauncher::class, ['uid' => $uid, 'sid' => $service->getId()]);

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
		$localStore = LocalService::contactsStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// initialize events data store
		$localStore = LocalService::eventsStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// initialize tasks data store
		$localStore = LocalService::tasksStore();
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
		$data = ['MailSupported' => false, 'ContactsSupported' => false, 'ContactsCollections' => [], 'EventsSupported' => false, 'EventsCollections' => [], 'TasksSupported' => false, 'TasksCollections' => []];
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user is the service owner
		if ($service->getUid() !== $uid) {
			return $data;
		}
		// create remote store client
		$remoteStore = RemoteService::freshClient($service);
		$remoteStore->connect();
		// retrieve collections for mail module
		if ($this->ConfigurationService->isMailAppAvailable() && $remoteStore->sessionCapable('mail')) {
			$data['MailSupported'] = true;
			$remoteMailService = RemoteService::mailService($remoteStore);
			try {
				$collections = $remoteMailService->collectionList();
				$data['MailCollections'] = array_map(function ($collection) {
					return ['id' => $collection->id(), 'label' => $collection->getLabel()];
				}, $collections);
			} catch (JmapUnknownMethod $e) {
				// AddressBook name space is not supported fail silently
			}
		}
		// retrieve collections for contacts module
		if ($this->ConfigurationService->isContactsAppAvailable() && $remoteStore->sessionCapable('contacts')) {
			$remoteContactsService = RemoteService::contactsService($remoteStore);
			try {
				$collections = $remoteContactsService->collectionList();
				$data['ContactsSupported'] = true;
				$data['ContactsCollections'] = array_map(function ($collection) {
					return ['id' => $collection->Id, 'label' => 'Personal - ' . $collection->Label];
				}, $collections);
			} catch (JmapUnknownMethod $e) {
				// AddressBook name space is not supported fail silently
			}
			// if AddressBook name space is not supported see if Contacts name space works
			if (count($data['ContactsCollections']) === 0) {
				try {
					$list = $remoteContactsService->entityList('', 'B');
					$data['ContactsSupported'] = true;
					$data['ContactsCollections'][] = ['id' => 'Default', 'label' => 'Personal - Contacts', 'count' => $list['total']];
				} catch (\Throwable $e) {
					// ContactCard name space is not supported fail silently
				}

			}
		}
		// retrieve collections for events module
		if ($this->ConfigurationService->isContactsAppAvailable() && $remoteStore->sessionCapable('calendars')) {
			$remoteEventsService = RemoteService::eventsService($remoteStore);
			try {
				$collections = $remoteEventsService->collectionList();
				$data['EventsSupported'] = true;
				$data['EventsCollections'] = array_map(function ($collection) {
					return ['id' => $collection->Id, 'label' => 'Personal - ' . $collection->Label];
				}, $collections);
			} catch (JmapUnknownMethod $e) {
				// AddressBook name space is not supported fail silently
			}
			// if AddressBook name space is not supported see if Contacts name space works
			if (count($data['EventsCollections']) === 0) {
				try {
					$list = $remoteEventsService->entityList('', 'B');
					$data['EventsSupported'] = true;
					$data['EventsCollections'][] = ['id' => 'Default', 'label' => 'Personal - Calendar', 'count' => $list['total']];
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
			$localStore = LocalService::contactsStore();
			$data['ContactCollections'] = $localStore->collectionListByService($sid);
		}
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			$localStore = LocalService::eventsStore();
			$data['EventCollections'] = $localStore->collectionListByService($sid);
		}
		if ($this->ConfigurationService->isTasksAppAvailable()) {
			$localStore = LocalService::tasksStore();
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
			$localStore = LocalService::contactsStore();
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
			$localStore = LocalService::eventsStore();
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
			$localStore = LocalService::tasksStore();
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
	 * @param string $subject notification type
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
