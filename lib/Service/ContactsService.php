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

use JmapClient\Client as JmapClient;

use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\ContactObject;
use OCA\JMAPC\Objects\HarmonizationStatisticsObject;
use OCA\JMAPC\Service\Local\LocalContactsService;
use OCA\JMAPC\Service\Remote\RemoteContactsService;
use OCA\JMAPC\Store\CollectionEntity;
use OCA\JMAPC\Store\ContactStore;

class ContactsService {
	
	private bool $debug;
	private string $userId;
	private Array $Configuration;
	private JmapClient $RemoteStore;
	
	public function __construct (
		private LoggerInterface $logger,
		private LocalContactsService $LocalContactsService,
		private RemoteContactsService $RemoteContactsService,
		private ContactStore $LocalStore
	) {}

	/**
	 * Perform harmonization for all events collections for a service
	 * 
	 * @since Release 1.0.0
	 *
	 * @return void
	 */
	public function harmonize(string $uid, $service, JmapClient $RemoteStore) {

		$this->userId = $uid;
		$this->Configuration = $service;
		$this->RemoteStore = $RemoteStore;
		//
		$this->debug = (bool)$service['debug'];
		// assign data stores
		$this->LocalContactsService->initialize($this->LocalStore);
		$this->RemoteContactsService->initialize($this->RemoteStore);

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
		$collections = $this->LocalStore->collectionListByService($this->Configuration['id']);
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
			// execute events harmonization loop
			do {
				// update lock heartbeat
				$collection->setHlockhb(time());
				$collection = $this->LocalStore->collectionModify($collection);
				// harmonize events collections
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
	 * Perform harmonization for all events in a collection
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
		$lcsn = $collection->getHisn();
		$rcid = $collection->getCcid();
		$rcsn = (string) $collection->getHesn();
		// delete and skip collection if remote id is missing
		if (empty($rcid)){
			$this->LocalContactsService->collectionDeleteById($lcid);
			$this->logger->debug('JMAPC - Deleted contacts collection for ' . $this->userId . ' due to missing Remote Id');
			return $statistics;
		}
		// delete and skip collection if remote collection is missing
		$remoteCollection = $this->RemoteContactsService->collectionFetch('', $rcid);
		if (!isset($remoteCollection)) {
			$this->LocalContactsService->collectionDeleteById($lcid);
			$this->logger->debug('JMAPC - Deleted contacts collection for ' . $this->userId . ' due to missing Remote Collection');
			return $statistics;
		}

		// retrieve a delta of remote entity variations
		// if server side delta is not available generate one
		try {
			$remoteCollectionDelta = $this->RemoteContactsService->entityDelta($rcid, $rcsn, 'B');
		} catch (JmapUnknownMethod $e) {
			$remoteCollectionDelta = $this->discoverRemoteAlteration($rcid, $lcid);
		}

		// process remote additions
		if (count($remoteCollectionDelta['Added']) > 0) {	
			foreach ($remoteCollectionDelta['Added'] as $reid) {
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
		if (count($remoteCollectionDelta['Modified']) > 0) {
			foreach ($remoteCollectionDelta['Modified'] as $reid) {
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
		if (count($remoteCollectionDelta['Deleted']) > 0) {
			foreach ($remoteCollectionDelta['Deleted'] as $reid) {
				// process delete
				$as = $this->harmonizeRemoteDelete(
					$this->userId,
					$sid,
					$rcid, 
					$reid
				);
				if ($as == 'LD') {
					// increment statistics
					$statistics->LocalDeleted += 1;
				}
			}
		}
		// update and deposit remote harmonization signature 
		$collection->setHesn((string)$remoteCollectionDelta['Signature']);
		$collection = $this->LocalStore->collectionModify($collection);
		// clean up
		unset($remoteCollection, $remoteCollectionDelta);
	
		// retrieve a delta of remote entity variations
		$localCollectionDelta = $this->LocalEventsService->entityDelta($lcid, $lcsn);
		// evaluate if local entity variations exist
		if (isset($localCollectionDelta['stamp'])) {
			// process local additions
			foreach ($localCollectionDelta['additions'] as $variant) {
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
			foreach ($localCollectionDelta['modifications'] as $variant) {
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
			foreach ($localCollectionDelta['deletions'] as $variant) {
				$leid = $variant['id'];
				// process deletion
				$as = $this->harmonizeLocalDelete(
					$this->userId,
					$lcid,
					$leid,
					$rcsn
				);
				if ($as == 'RD') {
					// assign status
					$statistics->RemoteDeleted += 1;
				}
			}
			// update and deposit correlation local state
			$collection->setHisn($localCollectionDelta['stamp']);
			$collection = $this->LocalStore->collectionModify($collection);
			// clean up
			unset($localCollectionDelta);
		}
		
		// return statistics
		return $statistics;

	}

	/**
	 * Perform harmonization for locally altered object
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		User ID
	 * @param int $lcid			Local Collection ID
	 * @param int $loid			Local Entity ID
	 * @param string $rcid		Remote Collection ID
	 * @param string $rcst		Remote Collection Signature Token
	 * @param int $caid			Correlation Affiliation ID
	 *
	 * @return string 			what action was performed
	 */
	function harmonizeLocalAltered ($uid, $lcid, $leid, $rcid, &$rcst, $caid): string {

		// // define default operation status
		$status = 'NA'; // no actions
		// define local entity place holder
		$lo = null;
		// define remote entity place holder
		$ro = null;
		// retrieve local contact object
		$lo = $this->LocalContactsService->entityFetch($leid);
		// evaluate, if local contact entity was returned
		if (!($lo instanceof \OCA\JMAPC\Objects\ContactObject)) {
			// return default operation status
			return $status;
		}
		// retrieve correlation for remote and local entity
		$ci = $this->CorrelationsService->findByLocalId($uid, CorrelationsService::ContactEntity, $leid, $lcid);
		// if correlation exists
		// compare local signature to correlation signature and stop processing if they match
		// this is nessary to prevent synconization feedback loop
		if ($ci instanceof \OCA\JMAPC\Store\Correlation && 
			$ci->getlosignature() == $lo->Signature) {
			// return default operation status
			return $status;
		}
		// if correlation exists, try to retrieve remote entity
		if ($ci instanceof \OCA\JMAPC\Store\Correlation && 
			!empty($ci->getroid())) {
			// retrieve entity
			$ro = $this->RemoteContactsService->entityFetch($ci->getrcid(), $rcst, $ci->getroid());
		}
		// modify remote entity if one EXISTS
		// create remote entity if one DOES NOT EXIST
		if ($ro instanceof \OCA\JMAPC\Objects\ContactObject) {
			// if correlation EXISTS
			// compare remote entity state to correlation signature
			// if signatures DO MATCH modify remote entity
			if ($ci instanceof \OCA\JMAPC\Store\Correlation && $ro->Signature == $ci->getrosignature()) {
				// update remote entity
				$ro = $this->RemoteContactsService->entityModify($ci->getrcid(), $rcst, $ci->getroid(), $lo);
				// assign operation status
				$status = 'RU'; // Remote Update
			}
			// if correlation DOES NOT EXIST or signatures DO NOT MATCH
			// use selected mode to resolve conflict
			else {
				// update local entity if remote wins mode selected
				if ($this->Configuration->ContactsPrevalence == 'R') {
					// append missing remote parameters from local object
					$ro->UUID = $lo->UUID;
					// update local entity
					$lo = $this->LocalContactsService->entityModify($uid, $lcid, $lo->ID, $ro);
					// assign operation status
					$status = 'LU'; // Local Update
				}
				// update remote entity if local wins mode selected
				if ($this->Configuration->ContactsPrevalence == 'L') {
					// update remote entity
					$ro = $this->RemoteContactsService->entityModify($rcid, $rcst, $ro->ID, $lo);
					// assign operation status
					$status = 'RU'; // Remote Update
				}
			}
		}
		else {
			// create remote entity
			$ro = $this->RemoteContactsService->entityCreate($rcid, $rcst, $lo);
			// assign operation status
			$status = 'RC'; // Remote Create
		}
		// update entity correlation if one EXISTS
		// create entity correlation if one DOES NOT EXIST
		if ($ci instanceof \OCA\JMAPC\Store\Correlation) {
			$ci->setloid($lo->ID); // Local ID
			$ci->setlosignature($lo->Signature); // Local Signature
			$ci->setlcid($lcid); // Local Collection ID
			$ci->setroid($ro->ID); // Remote ID
			$ci->setrosignature($ro->Signature); // Remote Signature
			$ci->setrcid($rcid); // Remote Collection ID
			$this->CorrelationsService->update($ci);
		}
		elseif (isset($ro) && isset($lo)) {
			$ci = new \OCA\JMAPC\Store\Correlation();
			$ci->settype(CorrelationsService::ContactEntity); // Correlation Type
			$ci->setuid($uid); // User ID
			$ci->setaid($caid); //Affiliation ID
			$ci->setloid($lo->ID); // Local ID
			$ci->setlosignature($lo->Signature); // Local Signature
			$ci->setlcid($lcid); // Local Collection ID
			$ci->setroid($ro->ID); // Remote ID
			$ci->setrosignature($ro->Signature); // Remote Signature
			$ci->setrcid($rcid); // Remote Collection ID
			$this->CorrelationsService->create($ci);
		}
		// return operation status
		return $status;

	}

	/**
	 * Perform harmonization for locally deleted entity
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid	user id
	 * @param string $lcid	local collection id
	 * @param string $loid	local entity id
	 * @param string $rcst	remote collection signature token
	 *
	 * @return string what action was performed
	 */
	function harmonizeLocalDelete ($uid, $lcid, $leid, &$rcst): string {

		// retrieve correlation
		$ci = $this->CorrelationsService->findByLocalId($uid, CorrelationsService::ContactEntity, $leid, $lcid);
		// validate result
		if ($ci instanceof \OCA\JMAPC\Store\Correlation) {
			// destroy remote entity
			$rs = $this->RemoteContactsService->entityDelete($ci->getrcid(), $rcst, $ci->getroid());
			// destroy correlation
			$this->CorrelationsService->delete($ci);
			// return status of action
			return 'RD';
		}
		else {
			// return status of action
			return 'NA';
		}
			
	}

	/**
	 * Perform harmonization for remotely altered entity
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid		User id
	 * @param string $rcid		remote collection id
	 * @param string $reid		remote object id
	 * @param string $lcid		local collection id
	 * @param string $caid		correlation affiliation id
	 *
	 * @return string 			what action was performed
	 */
	function harmonizeRemoteAltered ($uid, $rcid, $reid, $rdata, $lcid, $caid): string {
		
		// define default operation status
		$status = 'NA'; // no acction
		// define remote entity place holder
		$ro = null;
		// define local entity place holder
		$lo = null;
		// convert remote data to contact object
		$ro = $this->RemoteContactsService->toContactObject($rdata);
		// evaluate, if remote contact object was returned
		if (!($ro instanceof \OCA\JMAPC\Objects\ContactObject)) {
			// return default operation status
			return $status;
		}
		// append missing remote parameters from passed parameters
		$ro->ID = $reid;
		$ro->CID = $rcid;
		$ro->RCID = $rcid;
		$ro->REID = $reid;
		// generate a signature for the data
        // this a crude but nessary as EAS does not transmit a harmonization signature for entities
        $ro->Signature = $this->RemoteContactsService->generateSignature($ro);
		// retrieve correlation for remote and local entity
		$ci = $this->CorrelationsService->findByRemoteId($uid, CorrelationsService::ContactEntity, $reid, $rcid);
		// if correlation exists
		// compare remote generated signature to correlation signature and stop processing if they match
		// this is nessary to prevent synconization feedback loop
		if ($ci instanceof \OCA\JMAPC\Store\Correlation && 
			$ci->getrosignature() == $ro->Signature) {
			// return default operation status
			return $status;
		}
		// if correlation exists, try to retrieve local entity
		if ($ci instanceof \OCA\JMAPC\Store\Correlation && 
			$ci->getloid()) {			
			$lo = $this->LocalContactsService->entityFetch($ci->getloid());
		}
		// modify local entity if one EXISTS
		// create local entity if one DOES NOT EXIST
		if ($lo instanceof \OCA\JMAPC\Objects\ContactObject) {
			// if correlation EXISTS
			// compare local entity signature to correlation signature
			// if signatures DO MATCH modify local enitity
			if ($ci instanceof \OCA\JMAPC\Store\Correlation && $lo->Signature == $ci->getlosignature()) {
				// append missing remote parameters from local object
				$ro->UUID = $lo->UUID;
				// update local enitity
				$lo = $this->LocalContactsService->entityModify($uid, $ci->getlcid(), $ci->getloid(), $ro);
				// assign operation status
				$status = 'LU'; // Local Update
			}
			// if correlation DOES NOT EXIST or signatures DO NOT MATCH
			// use selected mode to resolve conflict
			else {
				// update local entity if remote wins mode selected
				if ($this->Configuration->ContactsPrevalence == 'R') {
					// append missing remote parameters from local object
					$ro->UUID = $lo->UUID;
					// update local entity
					$lo = $this->LocalContactsService->entityModify($uid, $lcid, $lo->ID, $ro);
					// assign operation status
					$status = 'LU'; // Local Update
				}
				// update remote entiry if local wins mode selected
				if ($this->Configuration->ContactsPrevalence == 'L') {
					// update remote entity
					$ro = $this->RemoteContactsService->entityModify($rcid, $ro->ID, '', $lo);
					// assign operation status
					$status = 'RU'; // Remote Update
				}
			}
		}
		else {
			// create local entity
			$lo = $this->LocalContactsService->entityCreate($uid, $lcid, $ro);
			// assign operation status
			$status = 'LC'; // Local Create
		}
		// update entity correlation if one EXISTS
		// create entity correlation if one DOES NOT EXIST
		if ($ci instanceof \OCA\JMAPC\Store\Correlation) {
			$ci->setloid($lo->ID); // Local ID
			$ci->setlosignature($lo->Signature); // Local Signature
			$ci->setlcid($lcid); // Local Collection ID
			$ci->setroid($ro->ID); // Remote ID
			$ci->setrosignature($ro->Signature); // Remote Signature
			$ci->setrcid($rcid); // Remote Collection ID
			$this->CorrelationsService->update($ci);
		}
		elseif (isset($ro) && isset($lo)) {
			$ci = new \OCA\JMAPC\Store\Correlation();
			$ci->settype(CorrelationsService::ContactEntity); // Correlation Type
			$ci->setuid($uid); // User ID
			$ci->setaid($caid); //Affiliation ID
			$ci->setloid($lo->ID); // Local ID
			$ci->setlosignature($lo->Signature); // Local Signature
			$ci->setlcid($lcid); // Local Collection ID
			$ci->setroid($ro->ID); // Remote ID
			$ci->setrosignature($ro->Signature); // Remote Signature
			$ci->setrcid($rcid); // Remote Collection ID
			$this->CorrelationsService->create($ci);
		}
		// return operation status
		return $status;

	}

	/**
	 * Perform harmonization for remotely deleted object
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $uid	nextcloud user id
	 * @param string $rcid	local collection id
	 * @param string $reid	local object id
	 *
	 * @return string what action was performed
	 */
	function harmonizeRemoteDelete ($uid, $rcid, $reid): string {

		// find correlation
		$ci = $this->CorrelationsService->findByRemoteId($uid, CorrelationsService::ContactEntity, $reid, $rcid);
		// evaluate correlation object
		if ($ci instanceof \OCA\JMAPC\Store\Correlation) {
			// destroy local entity
			$rs = $this->LocalContactsService->entityDelete($uid, $ci->getlcid(), $ci->getloid());
			// destroy correlation
			$this->CorrelationsService->delete($ci);
			// return operation status
			return 'LD';
		}
		else {
			// return operation status
			return 'NA';
		}

	}

}
