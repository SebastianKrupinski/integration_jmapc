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

use OCA\JMAPC\Service\Remote\RemoteCommonService;

use OCA\JMAPC\Service\Remote\RemoteService;
use Psr\Log\LoggerInterface;

class HarmonizationService {

	public function __construct(
		string $appName,
		private LoggerInterface $logger,
		private ConfigurationService $ConfigurationService,
		private CoreService $CoreService,
		private ServicesService $ServicesService,
		private RemoteCommonService $RemoteCommonService,
		private ContactsService $ContactsService,
		private EventsService $EventsService,
		private TasksService $TasksService,
		private HarmonizationThreadService $HarmonizationThreadService,
	) {
	}

	/**
	 * Perform harmonization for all modules
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param string $mode running mode (S - Service, M - Manually)
	 *
	 * @return void
	 */
	public function performHarmonization(string $uid, int $sid, string $mode = 'S'): void {

		// retrieve service
		$service = $this->ServicesService->fetchByUserIdAndServiceId($uid, $sid);
		// determine if we should run harmonization
		if (!$service->getEnabled() || !$service->getConnected()) {
			return;
		}
		// update harmonization state and start time
		$service->setHarmonizationState(true);
		$service->setHarmonizationStart(time());
		$service = $this->ServicesService->deposit($uid, $service);
		// initialize store(s)
		$remoteStore = RemoteService::initializeStoreFromEntity($service);

		// contacts
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			$this->logger->info('Started Harmonization of Contacts for ' . $uid);
			// assign configuration, data stores and harmonize
			$this->ContactsService->harmonize($uid, $service, $remoteStore);
			
			$this->logger->info('Finished Harmonization of Contacts for ' . $uid);
		}

		// events
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			$this->logger->info('Started Harmonization of Events for ' . $uid);
			// assign configuration, data stores and harmonize
			$this->EventsService->harmonize($uid, $service, $remoteStore);
			
			$this->logger->info('Finished Harmonization of Events for ' . $uid);
		}

		// tasks
		if ($this->ConfigurationService->isTasksAppAvailable()) {
			$this->logger->info('Started Harmonization of Tasks for ' . $uid);
			// assign configuration, data stores and harmonize
			//$this->TasksService->harmonize($uid, $service, $remoteStore);
			
			$this->logger->info('Finished Harmonization of Tasks for ' . $uid);
		}

		// update harmonization state and end time
		$service->setHarmonizationState(false);
		$service->setHarmonizationEnd(time());
		$service = $this->ServicesService->deposit($uid, $service);

		$this->logger->info('Finished Harmonization of Collections for ' . $uid);
	}

	/**
	 * Perform harmonization for all modules
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 *
	 * @return void
	 */
	public function performLiveHarmonization(string $uid): void {

		$this->logger->info('Started Live Harmonization of Collections for ' . $uid);

		// update harmonization state and start time
		$this->ConfigurationService->setHarmonizationState($uid, true);
		$this->ConfigurationService->setHarmonizationStart($uid);
		$this->ConfigurationService->setHarmonizationHeartBeat($uid);
		
		// retrieve Configuration
		$Configuration = $this->ConfigurationService->retrieveUser($uid);
		$Configuration = $this->ConfigurationService->toUserConfigurationObject($Configuration);
		// create remote store client
		$remoteStore = $this->CoreService->createClient($uid);

		// contacts harmonization
		/*
		try {
			// evaluate, if contacts app is available and contacts harmonization is turned on
			if ($this->ConfigurationService->isContactsAppAvailable() && $Configuration->ContactsHarmonize > 0) {
				$this->logger->info('Statred Harmonization of Contacts for ' . $uid);
				// assign remote data store
				$this->ContactsService->RemoteStore = $remoteStore;
				// retrieve list of collections correlations
				$collections = $this->CorrelationsService->findByType($uid, CorrelationsService::ContactCollection);
				// iterate through correlation items
				foreach ($collections as $collection) {
					// evaluate if correlation is locked and lock has not expired
					if ($collection->gethlock() == 1 &&
					   (time() - $collection->gethlockhb()) < 3600) {
						continue;
					}
					// evaluate, if current state is obsolete, by comparing timestamps
					if ($collection->gethperformed() > $collection->gethaltered()) {
						continue;
					}
					// lock correlation before harmonization
					$collection->sethlock(1);
					$collection->sethlockhd((int) getmypid());
					$this->CorrelationsService->update($collection);
					// execute contacts harmonization loop
					do {
						// update lock heartbeat
						$collection->sethlockhb(time());
						$this->CorrelationsService->update($collection);
						// harmonize contacts collections
						$statistics = $this->ContactsService->performHarmonization($collection, $Configuration);
						// evaluate if anything was done and publish notice if needed
						if ($statistics->total() > 0) {
							$this->CoreService->publishNotice($uid,'contacts_harmonized', (array)$statistics);
						}
					} while ($statistics->total() > 0);
					// update harmonization time stamp
					$collection->sethperformed(time());
					// unlock correlation after harmonization
					$collection->sethlock(0);
					$this->CorrelationsService->update($collection);
				}
				$this->logger->info('Finished Harmonization of Contacts for ' . $uid);
			}

		} catch (Exception $e) {

			throw new Exception($e, 1);

		}
		*/
		/*
		// events harmonization
		try {
			// evaluate, if calendar app is available and events harmonization is turned on
			if ($this->ConfigurationService->isCalendarAppAvailable() && $Configuration->EventsHarmonize > 0) {
				$this->logger->info('Statred Harmonization of Events for ' . $uid);
				// assign remote data store
				$this->EventsService->RemoteStore = $remoteStore;
				// retrieve list of correlations
				$collections = $this->CorrelationsService->findByType($uid, CorrelationsService::EventCollection);
				// iterate through correlation items
				foreach ($collections as $collection) {
					// evaluate if correlation is locked and lock has not expired
					if ($collection->gethlock() == 1 &&
					   (time() - $collection->gethlockhb()) < 3600) {
						continue;
					}
					// evaluate, if current state is obsolete, by comparing timestamps
					if ($collection->gethperformed() > $collection->gethaltered()) {
						continue;
					}
					// lock correlation before harmonization
					$collection->sethlock(1);
					$collection->sethlockhd((int) getmypid());
					$this->CorrelationsService->update($collection);
					// execute events harmonization loop
					do {
						// update lock heartbeat
						$collection->sethlockhb(time());
						$this->CorrelationsService->update($collection);
						// harmonize events collections
						$statistics = $this->EventsService->performHarmonization($collection, $Configuration);
						// evaluate if anything was done and publish notice if needed
						if ($statistics->total() > 0) {
							$this->CoreService->publishNotice($uid,'events_harmonized', (array)$statistics);
						}
					} while ($statistics->total() > 0);
					// update harmonization time stamp
					$collection->sethperformed(time());
					// unlock correlation after harmonization
					$collection->sethlock(0);
					$this->CorrelationsService->update($collection);
				}
				$this->logger->info('Finished Harmonization of Events for ' . $uid);
			}


		} catch (Exception $e) {

			throw new Exception($e, 1);

		}
		*/

		// tasks harmonization
		/*
		try {
			// evaluate, if tasks app is available and tasks harmonization is turned on
			if ($this->ConfigurationService->isTasksAppAvailable() && $Configuration->TasksHarmonize > 0) {
				$this->logger->info('Statred Harmonization of Tasks for ' . $uid);
				// assign remote data store
				$this->TasksService->RemoteStore = $remoteStore;
				// retrieve list of correlations
				$collections = $this->CorrelationsService->findByType($uid, CorrelationsService::TaskCollection);
				// iterate through correlation items
				foreach ($collections as $collection) {
					// evaluate if correlation is locked and lock has not expired
					if ($collection->gethlock() == 1 &&
					   (time() - $collection->gethlockhb()) < 3600) {
						continue;
					}
					// evaluate, if current state is obsolete, by comparing timestamps
					if ($collection->gethperformed() > $collection->gethaltered()) {
						continue;
					}
					// lock correlation before harmonization
					$collection->sethlock(1);
					$collection->sethlockhd((int) getmypid());
					$this->CorrelationsService->update($collection);
					// execute tasks harmonization loop
					do {
						// update lock heartbeat
						$collection->sethlockhb(time());
						$this->CorrelationsService->update($collection);
						// harmonize tasks collections
						$statistics = $this->TasksService->performHarmonization($collection, $Configuration);
						// evaluate if anything was done and publish notice if needed
						if ($statistics->total() > 0) {
							$this->CoreService->publishNotice($uid,'tasks_harmonized', (array)$statistics);
						}
					} while ($statistics->total() > 0);
					// update harmonization time stamp
					$collection->sethperformed(time());
					// unlock correlation after harmonization
					$collection->sethlock(0);
					$this->CorrelationsService->update($collection);
				}
				$this->logger->info('Finished Harmonization of Tasks for ' . $uid);
			}

		} catch (Exception $e) {

			throw new Exception($e, 1);

		}
		*/
		// update harmonization state and end time
		$this->ConfigurationService->setHarmonizationState($uid, false);
		$this->ConfigurationService->setHarmonizationEnd($uid);
	}
	
	public function connectEvents(string $uid, int $duration, string $ctype): ?object {

		// retrieve correlations
		$cc = $this->CorrelationsService->findByType($uid, $ctype);
		// evaluate if any correlation where found
		if (count($cc) > 0) {
			// extract correlation ids
			$ids = array_map(function ($o) { return $o->getroid();}, $cc);
			// create remote store client
			$remoteStore = $this->CoreService->createClient($uid);
			// execute command
			$rs = $this->RemoteCommonService->connectEvents($remoteStore, $duration, $ids, null, ['CreatedEvent', 'ModifiedEvent', 'DeletedEvent', 'CopiedEvent', 'MovedEvent']);
		}
		// return id and token
		if ($rs instanceof \stdClass) {
			return $rs;
		} else {
			return null;
		}

	}

	public function disconnectEvents(string $uid, string $id): ?bool {

		// create remote store client
		$remoteStore = $this->CoreService->createClient($uid);
		// execute command
		$rs = $this->RemoteCommonService->disconnectEvents($remoteStore, $id);
		// return response
		return $rs;

	}

	public function consumeEvents(string $uid, string $id, string $token, string $ctype): ?object {

		// construct state place holder
		$state = false;
		// create remote store client
		$remoteStore = $this->CoreService->createClient($uid);
		// execute command
		$rs = $this->RemoteCommonService->fetchEvents($remoteStore, $id, $token);
		
		if (isset($rs->CreatedEvent)) {
			foreach ($rs->CreatedEvent as $entry) {
				// do nothing
			}
		}

		if (isset($rs->ModifiedEvent)) {
			foreach ($rs->ModifiedEvent as $entry) {
				// evaluate, if it was an collection event, ignore object events
				if (isset($entry->FolderId)) {
					// extract atributes
					$cid = $entry->FolderId->Id;
					$cstate = $entry->FolderId->ChangeKey;
					// retrieve collection correlation
					$cc = $this->CorrelationsService->findByRemoteId($uid, $ctype, $cid);
					// evaluate correlation, if exists, change altered time stamp
					if ($cc instanceof \OCA\JMAPC\Store\Correlation) {
						$cc->sethaltered(time());
						$this->CorrelationsService->update($cc);
						$state = true;
					}
					// aquire water mark
					$token = $entry->Watermark;
				}
				
				$w[] = ['C', ($entry->Watermark == $rs->PreviousWatermark), $entry->Watermark];
			}
		}

		if (isset($rs->DeletedEvent)) {
			foreach ($rs->DeletedEvent as $entry) {
				// do nothing
			}
		}

		if (isset($rs->CopiedEvent)) {
			foreach ($rs->CopiedEvent as $entry) {
				// do nothing
			}
		}

		if (isset($rs->MovedEvent)) {
			foreach ($rs->MovedEvent as $entry) {
				// do nothing
			}
		}

		if (isset($rs->StatusEvent)) {
			foreach ($rs->StatusEvent as $entry) {
				$token = $entry->Watermark;
			}
		}

		// return response
		return (object)['Id' => $id, 'Token' => $token, 'signature' => $state];

	}

}
