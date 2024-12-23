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

use Psr\Log\LoggerInterface;
use OCP\Files\IRootFolder;

use JmapClient\Client as JmapClient;

use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\HarmonizationStatisticsObject;
use OCA\JMAPC\Service\Local\LocalEventsService;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use OCA\JMAPC\Store\CollectionEntity;
use OCA\JMAPC\Store\EventStore;
use OCA\JMAPC\Store\ServiceEntity;

class EventsService {
	
	private bool $debug;
	private string $userId;
	private ServiceEntity $Configuration;
	private JmapClient $RemoteStore;

	public function __construct (
		private LoggerInterface $logger,
		private LocalEventsService $LocalEventsService,
		private RemoteEventsService $RemoteEventsService,
		private EventStore $LocalStore,
		private IRootFolder $LocalFileStore
	) {}

	/**
	 * Perform harmonization for all collections for a service
	 * 
	 * @since Release 1.0.0
	 *
	 * @return void
	 */
	public function harmonize(string $uid, ServiceEntity $service, JmapClient $RemoteStore) {

		$this->userId = $uid;
		$this->Configuration = $service;
		$this->RemoteStore = $RemoteStore;
		//
		$this->debug = $service->getDebug();
		// assign data stores
		$this->LocalEventsService->initialize($this->LocalStore, $this->LocalFileStore->getUserFolder($this->userId));
		$this->RemoteEventsService->initialize($this->RemoteStore);

		// assign timezones
		/*
		$this->LocalEventsService->SystemTimeZone = $this->Configuration->SystemTimeZone;
		$this->RemoteEventsService->SystemTimeZone = $this->Configuration->SystemTimeZone;
		$this->LocalEventsService->UserTimeZone = $this->Configuration->UserTimeZone;
		$this->RemoteEventsService->UserTimeZone = $this->Configuration->UserTimeZone;
		// assign default folder
		$this->LocalEventsService->UserAttachmentPath = $this->Configuration->EventsAttachmentPath;
		*/

		// retrieve list of collections
		$collections = $this->LocalStore->collectionListByService($this->Configuration->getId());
		// iterate through collections
		foreach ($collections as $collection) {
			// evaluate if collection is locked and lock has not expired
			if ($collection->getHlock() == 1 &&
			   (time() - $collection->getHlockhb()) < 3600) {
				continue;
			}
			// lock collection before harmonization
			if (!$this->debug) {
				$collection->setHlock(1);
			}
			$collection->setHlockhd((int) getmypid());
			$collection = $this->LocalStore->collectionModify($collection);
			// execute harmonization loop
			do {
				// update lock heartbeat
				$collection->setHlockhb(time());
				$collection = $this->LocalStore->collectionModify($collection);
				// harmonize collections
				$statistics = $this->harmonizeCollection($collection);
				// evaluate if anything was done and publish notice if needed
				if ($statistics->total() > 0) {
					//$this->CoreService->publishNotice($uid,'events_harmonized', (array)$statistics);
				}
			} while ($statistics->total() > 0);
			// update harmonization time stamp
			$collection->setHlockhb(time());
			// unlock correlation after harmonization
			$collection->setHlock(0);
			$collection = $this->LocalStore->collectionModify($collection);
		}

	}

	/**
	 * Perform harmonization for all entities in a collection
	 * 
	 * @since Release 1.0.0
	 *
	 * @return HarmonizationStatisticsObject
	 */
	public function harmonizeCollection(CollectionEntity $collection): HarmonizationStatisticsObject {
		
		// define statistics object
		$statistics = new HarmonizationStatisticsObject();
		// determine that the correlation belongs to the initialized user
		if ($collection->getUid() !== $this->userId) {
			return $statistics;
		}
		// extract required id's
		$sid = $collection->getSid();
		$lcid = $collection->getId();
		$lcsn = (string) $collection->getHisn();
		$rcid = $collection->getCcid();
		$rcsn = (string) $collection->getHesn();
		// delete and skip collection if remote id is missing
		if (empty($rcid)){
			$this->LocalEventsService->collectionDeleteById($lcid);
			$this->logger->debug('JMAPC - Deleted events collection for ' . $this->userId . ' due to missing Remote Id');
			return $statistics;
		}
		// delete and skip collection if remote collection is missing
		$remoteCollection = $this->RemoteEventsService->collectionFetch('', $rcid);
		if (!isset($remoteCollection)) {
			$this->LocalEventsService->collectionDeleteById($lcid);
			$this->logger->debug('JMAPC - Deleted events collection for ' . $this->userId . ' due to missing Remote Collection');
			return $statistics;
		}

		// retrieve a delta of local entity variations
		$localEntityDelta = $this->LocalEventsService->entityDelta($lcid, $lcsn);
		// retrieve a delta of remote entity variations
		// if server side delta is not available generate one
		try {
			$remoteEntityDelta = $this->RemoteEventsService->entityDelta($rcid, $rcsn, 'B');
		} catch (JmapUnknownMethod $e) {
			$remoteEntityDelta = $this->discoverRemoteAlteration($rcid, $lcid);
		}

		// process remote additions
		if (count($remoteEntityDelta['Added']) > 0) {
			foreach ($remoteEntityDelta['Added'] as $reid) {
				// process addition
				$as = $this->harmonizeRemoteAltered(
					$this->userId,
					$sid,
					$rcid,
					$reid,
					$lcid
				);
				// increment statistics
				switch ($as) {
					case 'LC':
						$statistics->LocalCreated += 1;
						break;
					case 'LU':
						$statistics->LocalUpdated += 1;
						break;
					case 'RU':
						$statistics->RemoteUpdated += 1;
						break;
				}
			}
		}
		
		// process remote modifications
		if (count($remoteEntityDelta['Modified']) > 0) {
			foreach ($remoteEntityDelta['Modified'] as $reid) {
				// process modification
				$as = $this->harmonizeRemoteAltered(
					$this->userId,
					$sid,
					$rcid,
					$reid,
					$lcid
				);
				// increment statistics
				switch ($as) {
					case 'LC':
						$statistics->LocalCreated += 1;
						break;
					case 'LU':
						$statistics->LocalUpdated += 1;
						break;
					case 'RU':
						$statistics->RemoteUpdated += 1;
						break;
				}
			}
		}
		
		// process remote deletions
		if (count($remoteEntityDelta['Deleted']) > 0) {
			foreach ($remoteEntityDelta['Deleted'] as $reid) {
				// process delete
				$as = $this->harmonizeRemoteDelete(
					$rcid, 
					$reid,
					$lcid
				);
				if ($as == 'LD') {
					// increment statistics
					$statistics->LocalDeleted += 1;
				}
			}
		}
		// update and deposit remote harmonization signature 
		$collection->setHesn((string)$remoteEntityDelta['Signature']);
		$collection = $this->LocalStore->collectionModify($collection);
		// clean up
		unset($remoteCollection, $remoteEntityDelta);

		// evaluate if local entity variations exist
		if (isset($localEntityDelta['stamp'])) {
			// process local additions
			foreach ($localEntityDelta['additions'] as $variant) {
				$leid = $variant['id'];
				// process addition
				$as = $this->harmonizeLocalAltered(
					$this->userId,
					$sid,
					$lcid, 
					$leid, 
					$rcid,
					$rcsn
				);
				// increment statistics
				switch ($as) {
					case 'RC':
						$statistics->RemoteCreated += 1;
						break;
					case 'RU':
						$statistics->RemoteUpdated += 1;
						break;
					case 'LU':
						$statistics->LocalUpdated += 1;
						break;
				}
			}
			// process local modifications
			foreach ($localEntityDelta['modifications'] as $variant) {
				$leid = $variant['id'];
				// process modification
				$as = $this->harmonizeLocalAltered(
					$this->userId,
					$sid,
					$lcid,
					$leid,
					$rcid,
					$rcsn,
				);
				// increment statistics
				switch ($as) {
					case 'RC':
						$statistics->RemoteCreated += 1;
						break;
					case 'RU':
						$statistics->RemoteUpdated += 1;
						break;
					case 'LU':
						$statistics->LocalUpdated += 1;
						break;
				}
			}
			// process local deletions
			foreach ($localEntityDelta['deletions'] as $variant) {
				$leid = $variant['id'];
				// process deletion
				$as = $this->harmonizeLocalDelete(
					$leid
				);
				if ($as == 'RD') {
					// assign status
					$statistics->RemoteDeleted += 1;
				}
			}
			// update and deposit correlation local state
			$collection->setHisn($localEntityDelta['stamp']);
			$collection = $this->LocalStore->collectionModify($collection);
			// clean up
			unset($localEntityDelta);
		}
		
		// return statistics
		return $statistics;

	}

	public function discoverRemoteAlteration(string $rcid, int $lcid): array {
		// retrieve remote entity list and local entity list
		$rList = $this->RemoteEventsService->entityList($rcid, null, null, 'B');
		$lList = $this->LocalEventsService->entityList($lcid, 'B');

		$lList = array_reduce($lList, function ($list, $entry) {
			if (!empty($entry->getCeid())) {
				$list[$entry->getCeid()] = $entry;
			}
			return $list;
		}, []);

		$dList = ['Added' => [], 'Modified' => [], 'Deleted' => [], 'Signature' => ''];

		// iterate through remote entities to find entities that do and don't exist in correlations 
		foreach ($rList['list'] as $entry) {
			//
			if (!$entry->in() || !in_array($rcid, $entry->in())) {
				continue;
			}
			// determine if entry exists in local list
			// if found add entity to modified delta and remove from local list
			// if NOT found add entity to added delta
			if (isset($lList[$entry->id()])) {
				if ($lList[$entry->id()]->getCesn() !== (string)$entry->updated()->getTimestamp()) {
					$dList['Modified'][] = $entry->id();
				}
				unset($lList[$entry->id()]);
			} else {
				$dList['Added'][] = $entry->id();
			}
		}
		$dList['Signature'] = $rList['state'];
		// iterate through remaining correlations
		// if a correlation that was not removed it must have been deleted on the remote system
		foreach ($lList as $entry) {
			$dList['Deleted'][] = $entry->getCeid();
		}

		return $dList;
	}
	
	/**
	 * harmonize locally altered entity
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		system user id
	 * @param int $sid			service id
	 * @param int $lcid			local collection id
	 * @param int $leid			local entity id
	 * @param string $rcid		remote collection id
	 *
	 * @return string 			what action was performed
	 */
	public function harmonizeLocalAltered(string $uid, int $sid, int $lcid, int $leid, string $rcid): string {

		// // define default operation status
		$status = 'NA'; // no actions
		// define entity place holder
		$lo = null;
		$ro = null;
		// retrieve local entity
		$lo = $this->LocalEventsService->entityFetch($leid);
		// evaluate, if local entity was returned
		if (!($lo instanceof EventObject)) {
			return $status;
		}
		// retrieve remote entity with correlation collection and entity id
		if (!empty($lo->CEID)) {
			$ro = $this->RemoteEventsService->entityFetch($rcid, $lo->CEID);
		}
		// if remote entity exists
		// compare remote generated signature to correlation signature and stop processing if they match
		// this is necessary to prevent synchronization feedback loop
		if ($ro instanceof EventObject && $lo->CESN === (string)$ro->ModifiedOn->getTimestamp()) {
			return $status;
		}
		// modify remote entity if one EXISTS
		// create remote entity if one DOES NOT EXIST
		if ($ro instanceof EventObject) {
			// update remote entity
			$ro = $this->RemoteEventsService->entityModify($rcid, $ro->ID, $lo);
			// update local entity
			if ($ro instanceof EventObject) {
				$ro->CCID = $rcid; // remote collection id
				$ro->CEID = $ro->ID; // remote entity id
				$ro->CESN = (string)$ro->ModifiedOn->getTimestamp(); // remote entity signature
				$this->LocalEventsService->entityModify($uid, $sid, $lcid, $leid, $ro);
			}
			// assign operation status
			$status = 'RU'; // Remote Update
		}
		else {
			// create remote entity
			$ro = $this->RemoteEventsService->entityCreate($rcid, $lo);
			// update local entity
			if ($ro instanceof EventObject) {
				$ro->CCID = $rcid; // remote collection id
				$ro->CEID = $ro->ID; // remote entity id
				$ro->CESN = (string)$ro->ModifiedOn->getTimestamp(); // remote entity signature
				$this->LocalEventsService->entityModify($uid, $sid, $lcid, $leid, $ro);
			}
			// assign operation status
			$status = 'RC'; // Remote Create
		}
		// return operation status
		return $status;

	}

	/**
	 * harmonize locally deleted entity
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param int $lcid			local collection id
	 * @param int $leid			local entity id
	 *
	 * @return string			what action was performed
	 */
	public function harmonizeLocalDelete(int $leid): string {

		// retrieve local entity
		$lo = $this->LocalEventsService->entityFetch($leid);
		// evaluate, if local entity was returned
		if (!($lo instanceof EventObject)) {
			return 'NA';
		}

		// destroy remote entity
		$rs = $this->RemoteEventsService->entityDelete($lo->CCID, $lo->CEID);
		
		if ($rs) {
			return 'RD';
		} else {
			return 'NA';
		}
			
	}

	/**
	 * harmonize remotely altered entity
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		system user id
	 * @param int $sid			service id
	 * @param string $rcid		remote collection id
	 * @param string $reid		remote entity id
	 * @param int $lcid			local collection id
	 * 
	 * @return string 			what action was performed
	 */
	public function harmonizeRemoteAltered(string $uid, int $sid, string $rcid, string $reid, int $lcid): string {
		
		// define default operation status
		$status = 'NA'; // no action
		// define entity place holders
		$ro = null;
		$lo = null;
		// retrieve remote entity
		$ro = $this->RemoteEventsService->entityFetch($rcid, $reid);
		// evaluate, if remote entity was returned
		if (!($ro instanceof EventObject)) {
			return $status;
		}
		// retrieve local entity with remote collection and entity id
		$lo = $this->LocalEventsService->entityFetchByCorrelation($lcid, $rcid, $reid);
		// if local entity exists
		// compare remote generated signature to correlation signature and stop processing if they match
		// this is necessary to prevent synchronization feedback loop
		if ($lo instanceof EventObject && $lo->CESN === (string)$ro->ModifiedOn->getTimestamp()) {
			return $status;
		}
		// modify local entity if one EXISTS
		// create local entity if one DOES NOT EXIST
		if ($lo instanceof EventObject) {
			// assign missing parameters
			$ro->UUID = $lo->UUID;
			$ro->CCID = $rcid;
			$ro->CEID = $reid;
			$ro->CESN = (string)$ro->ModifiedOn->getTimestamp();
			// update local entity
			$lo = $this->LocalEventsService->entityModify($uid, $sid, $lcid, (int)$lo->ID, $ro);
			// assign operation status
			$status = 'LU'; // Local Update
		}
		else {
			// assign missing parameters
			$ro->CCID = $rcid;
			$ro->CEID = $reid;
			$ro->CESN = (string)$ro->ModifiedOn->getTimestamp();
			// create local entity
			$lo = $this->LocalEventsService->entityCreate($uid, $sid, $lcid, $ro);
			// assign operation status
			$status = 'LC'; // Local Create
		}
		// return operation status
		return $status;

	}

	/**
	 * harmonize remotely deleted entity
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $rcid		remote collection id
	 * @param string $reid		remote entity id
	 * @param int $lcid			local collection id
	 *
	 * @return string			what action was performed
	 */
	public function harmonizeRemoteDelete(string $rcid, string $reid, int $lcid): string {

		// destroy local entity
		$rs = $this->LocalEventsService->entityDeleteByCorrelation($lcid, $rcid, $reid);
		
		if ($rs) {
			return 'LD';
		} else {
			return 'NA';
		}

	}
	
}
