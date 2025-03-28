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

use OCA\JMAPC\Objects\HarmonizationStatisticsObject;
use OCA\JMAPC\Service\Local\LocalTasksService;

use OCA\JMAPC\Service\Remote\RemoteTasksService;
use OCA\JMAPC\Store\Local\TaskStore;
use OCA\JMAPC\Utile\Eas\EasClient;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class TasksService {
	
	private LoggerInterface $logger;
	private Object $Configuration;
	private LocalTasksService $LocalTasksService;
	private RemoteTasksService $RemoteTasksService;
	private IRootFolder $LocalFileStore;
	private TaskStore $LocalStore;
	private EasClient $RemoteStore;

	public function __construct(LoggerInterface $logger,
		LocalTasksService $LocalTasksService,
		RemoteTasksService $RemoteTasksService,
		IRootFolder $LocalFileStore,
		TaskStore $LocalStore) {
		$this->logger = $logger;
		$this->LocalTasksService = $LocalTasksService;
		$this->RemoteTasksService = $RemoteTasksService;
		$this->LocalStore = $LocalStore;
		$this->LocalFileStore = $LocalFileStore;
	}

	public function initialize($configuration, EasClient $RemoteStore) {

		$this->Configuration = $configuration;
		$this->RemoteStore = $RemoteStore;
		// assign data stores
		$this->LocalTasksService->initialize($this->LocalStore, $this->LocalFileStore->getUserFolder($this->Configuration->UserId));
		$this->RemoteTasksService->initialize($this->RemoteStore);

		// assign timezones
		$this->LocalTasksService->SystemTimeZone = $this->Configuration->SystemTimeZone;
		$this->RemoteTasksService->SystemTimeZone = $this->Configuration->SystemTimeZone;
		$this->LocalTasksService->UserTimeZone = $this->Configuration->UserTimeZone;
		$this->RemoteTasksService->UserTimeZone = $this->Configuration->UserTimeZone;
		// assign default folder
		$this->LocalTasksService->UserAttachmentPath = $this->Configuration->TasksAttachmentPath;

	}

	/**
	 * Perform harmonization for all contacts collection correlations
	 *
	 * @since Release 1.0.0
	 *
	 * @return HarmonizationStatisticsObject
	 */
	public function performHarmonization($correlation, $configuration): HarmonizationStatisticsObject {
		
		// define statistics object
		$statistics = new HarmonizationStatisticsObject();
		// extract required id's
		$caid = $correlation->getid();
		$lcid = $correlation->getloid();
		$lcst = (string)$correlation->getlosignature();
		$rcid = $correlation->getroid();
		$rcst = (string)$correlation->getrosignature();
		// delete and skip collection correlation if remote id or local id is missing
		if (empty($lcid) || empty($rcid)) {
			$this->CorrelationsService->delete($correlation);
			$this->logger->debug('EAS - Deleted tasks collection correlation for ' . $this->Configuration->UserId . ' due to missing Remote ID or Local ID');
			return $statistics;
		}
		// delete and skip collection correlation if local collection is missing
		$lcollection = $this->LocalTasksService->collectionFetch($lcid);
		if (!isset($lcollection) || ($lcollection->Id != $lcid)) {
			$this->CorrelationsService->delete($correlation);
			$this->logger->debug('EAS - Deleted tasks collection correlation for ' . $this->Configuration->UserId . ' due to missing Local Collection');
			return $statistics;
		}
		// delete and skip collection correlation if remote collection is missing
		//$rcollection = $this->RemoteTasksService->collectionFetch(0, 0, $rcid);
		//if (!isset($rcollection) || ($rcollection->Id != $rcid)) {
		//	$this->CorrelationsService->delete($correlation);
		//	$this->logger->debug('EAS - Deleted tasks collection correlation for ' . $this->Configuration->UserId . ' due to missing Remote Collection');
		//	return $statistics;
		//}

		// retrieve a collection of local entity variations
		//$lCollectionDelta = [];
		$lCollectionDelta = $this->LocalTasksService->reconcileCollection($this->Configuration->UserId, $lcid, $lcst);
		// evaluate if local entity variations exist
		if (isset($lCollectionDelta['stamp'])) {
			// process local additions
			foreach ($lCollectionDelta['additions'] as $variant) {
				// process addition
				$as = $this->harmonizeLocalAltered(
					$this->Configuration->UserId,
					$lcid,
					$variant['id'],
					$rcid,
					$rcst,
					$caid
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
			foreach ($lCollectionDelta['modifications'] as $variant) {
				// process modification
				$as = $this->harmonizeLocalAltered(
					$this->Configuration->UserId,
					$lcid,
					$variant['id'],
					$rcid,
					$rcst,
					$caid
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
			foreach ($lCollectionDelta['deletions'] as $variant) {
				// process deletion
				$as = $this->harmonizeLocalDelete(
					$this->Configuration->UserId,
					$lcid,
					$variant['id'],
					$rcst
				);
				if ($as == 'RD') {
					// assign status
					$statistics->RemoteDeleted += 1;
				}
			}
			// update and deposit correlation local state
			$correlation->setlosignature($lCollectionDelta['stamp']);
			$correlation->setrosignature($rcst);
			$this->CorrelationsService->update($correlation);
		}

		// retrieve a collection of remote entity variations
		//$rCollectionDelta = [];
		$rCollectionDelta = $this->RemoteTasksService->reconcileCollection($rcid, $rcst);
		// evaluate if remote entity variations exist
		// according to the EAS spec the change object can be blank if there is no changes
		if (isset($rCollectionDelta->SyncKey)) {
			//
			$rcst = $rCollectionDelta->SyncKey->getContents();
			// evaluate if add property is an array and convert to array if needed
			if (isset($rCollectionDelta->Commands->Add) && !is_array($rCollectionDelta->Commands->Add)) {
				$rCollectionDelta->Commands->Add = [$rCollectionDelta->Commands->Add];
			}
			// process remote additions
			foreach ($rCollectionDelta->Commands->Add as $variant) {
				// process addition
				$as = $this->harmonizeRemoteAltered(
					$this->Configuration->UserId,
					$rcid,
					$variant->EntityId->getContents(),
					$variant->Data,
					$lcid,
					$caid
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
			// evaluate if modify property is an array and convert to array if needed
			if (isset($rCollectionDelta->Commands->Modify) && !is_array($rCollectionDelta->Commands->Modify)) {
				$rCollectionDelta->Commands->Modify = [$rCollectionDelta->Commands->Modify];
			}
			// process remote modifications
			foreach ($rCollectionDelta->Commands->Modify as $Altered) {
				// process modification
				$as = $this->harmonizeRemoteAltered(
					$this->Configuration->UserId,
					$rcid,
					$Altered->EntityId->getContents(),
					$Altered->Data,
					$lcid,
					$caid
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
			// evaluate if delete property is an array and convert to array if needed
			if (isset($rCollectionDelta->Commands->Delete) && !is_array($rCollectionDelta->Commands->Delete)) {
				$rCollectionDelta->Commands->Delete = [$rCollectionDelta->Commands->Delete];
			}
			// process remote deletions
			foreach ($rCollectionDelta->Commands->Delete as $Deleted) {
				// process delete
				$as = $this->harmonizeRemoteDelete(
					$this->Configuration->UserId,
					$rcid,
					$Deleted->EntityId->getContents()
				);
				if ($as == 'LD') {
					// increment statistics
					$statistics->LocalDeleted += 1;
				}
			}
			// update and deposit correlation remote state
			$correlation->setrosignature($rcst);
			$this->CorrelationsService->update($correlation);
		}
		
		// return statistics
		return $statistics;

	}

	/**
	 * Perform harmonization for locally altered object
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid User ID
	 * @param int $lcid Local Collection ID
	 * @param int $loid Local Entity ID
	 * @param string $rcid Remote Collection ID
	 * @param string $rcst Remote Collection Signature Token
	 * @param int $caid Correlation Affiliation ID
	 *
	 * @return string what action was performed
	 */
	public function harmonizeLocalAltered($uid, $lcid, $leid, $rcid, &$rcst, $caid): string {

		// // define default operation status
		$status = 'NA'; // no actions
		// define local entity place holder
		$lo = null;
		// define remote entity place holder
		$ro = null;
		// retrieve local contact object
		$lo = $this->LocalTasksService->entityFetch($leid);
		// evaluate, if local contact entity was returned
		if (!($lo instanceof \OCA\JMAPC\Objects\TaskObject)) {
			// return default operation status
			return $status;
		}
		// retrieve correlation for remote and local entity
		$ci = $this->CorrelationsService->findByLocalId($uid, CorrelationsService::TaskEntity, $leid, $lcid);
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
			$ro = $this->RemoteTasksService->entityFetch($ci->getrcid(), $rcst, $ci->getroid());
			// generate a signature for the data
			// this a crude but nessary as EAS does not transmit a harmonization signature for entities
			$ro->Signature = $this->generateSignature($ro);
		}
		// modify remote entity if one EXISTS
		// create remote entity if one DOES NOT EXIST
		if ($ro instanceof \OCA\JMAPC\Objects\TaskObject) {
			// if correlation EXISTS
			// compare remote entity state to correlation signature
			// if signatures DO MATCH modify remote entity
			if ($ci instanceof \OCA\JMAPC\Store\Correlation && $ro->Signature == $ci->getrosignature()) {
				// update remote entity
				$ro = $this->RemoteTasksService->entityModify($ci->getrcid(), $rcst, $ci->getroid(), $lo);
				// assign operation status
				$status = 'RU'; // Remote Update
			}
			// if correlation DOES NOT EXIST or signatures DO NOT MATCH
			// use selected mode to resolve conflict
			else {
				// update local entity if remote wins mode selected
				if ($this->Configuration->TasksPrevalence == 'R') {
					// append missing remote parameters from local object
					$ro->UUID = $lo->UUID;
					// update local entity
					$lo = $this->LocalTasksService->entityModify($uid, $lcid, $lo->ID, $ro);
					// assign operation status
					$status = 'LU'; // Local Update
				}
				// update remote entity if local wins mode selected
				if ($this->Configuration->TasksPrevalence == 'L') {
					// update remote entity
					$ro = $this->RemoteTasksService->entityModify($rcid, $rcst, $ro->ID, $lo);
					// assign operation status
					$status = 'RU'; // Remote Update
				}
			}
		} else {
			// create remote entity
			$ro = $this->RemoteTasksService->entityCreate($rcid, $rcst, $lo);
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
		} elseif (isset($ro) && isset($lo)) {
			$ci = new \OCA\JMAPC\Store\Correlation();
			$ci->settype(CorrelationsService::TaskEntity); // Correlation Type
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
	 * @param string $uid user id
	 * @param string $lcid local collection id
	 * @param string $loid local entity id
	 * @param string $rcst remote collection signature token
	 *
	 * @return string what action was performed
	 */
	public function harmonizeLocalDelete($uid, $lcid, $leid, &$rcst): string {

		// retrieve correlation
		$ci = $this->CorrelationsService->findByLocalId($uid, CorrelationsService::TaskEntity, $leid, $lcid);
		// validate result
		if ($ci instanceof \OCA\JMAPC\Store\Correlation) {
			// destroy remote entity
			$rs = $this->RemoteTasksService->entityDelete($ci->getrcid(), $rcst, $ci->getroid());
			// destroy correlation
			$this->CorrelationsService->delete($ci);
			// return status of action
			return 'RD';
		} else {
			// return status of action
			return 'NA';
		}
			
	}

	/**
	 * Perform harmonization for remotely altered entity
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid User id
	 * @param string $rcid remote collection id
	 * @param string $reid remote object id
	 * @param string $lcid local collection id
	 * @param string $caid correlation affiliation id
	 *
	 * @return string what action was performed
	 */
	public function harmonizeRemoteAltered($uid, $rcid, $reid, $rdata, $lcid, $caid): string {
		
		// define default operation status
		$status = 'NA'; // no acction
		// define remote entity place holder
		$ro = null;
		// define local entity place holder
		$lo = null;
		// convert remote data to contact object
		$ro = $this->RemoteTasksService->toTaskObject($rdata);
		// evaluate, if remote contact object was returned
		if (!($ro instanceof \OCA\JMAPC\Objects\TaskObject)) {
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
		$ro->Signature = $this->generateSignature($ro);
		// retrieve correlation for remote and local entity
		$ci = $this->CorrelationsService->findByRemoteId($uid, CorrelationsService::TaskEntity, $reid, $rcid);
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
			$lo = $this->LocalTasksService->entityFetch($ci->getloid());
		}
		// modify local entity if one EXISTS
		// create local entity if one DOES NOT EXIST
		if ($lo instanceof \OCA\JMAPC\Objects\TaskObject) {
			// if correlation EXISTS
			// compare local entity signature to correlation signature
			// if signatures DO MATCH modify local enitity
			if ($ci instanceof \OCA\JMAPC\Store\Correlation && $lo->Signature == $ci->getlosignature()) {
				// append missing remote parameters from local object
				$ro->UUID = $lo->UUID;
				// update local enitity
				$lo = $this->LocalTasksService->entityModify($uid, $ci->getlcid(), $ci->getloid(), $ro);
				// assign operation status
				$status = 'LU'; // Local Update
			}
			// if correlation DOES NOT EXIST or signatures DO NOT MATCH
			// use selected mode to resolve conflict
			else {
				// update local entity if remote wins mode selected
				if ($this->Configuration->TasksPrevalence == 'R') {
					// append missing remote parameters from local object
					$ro->UUID = $lo->UUID;
					// update local entity
					$lo = $this->LocalTasksService->entityModify($uid, $lcid, $lo->ID, $ro);
					// assign operation status
					$status = 'LU'; // Local Update
				}
				// update remote entiry if local wins mode selected
				if ($this->Configuration->TasksPrevalence == 'L') {
					// update remote entity
					$ro = $this->RemoteTasksService->entityModify($rcid, $ro->ID, '', $lo);
					// assign operation status
					$status = 'RU'; // Remote Update
				}
			}
		} else {
			// create local entity
			$lo = $this->LocalTasksService->entityCreate($uid, $lcid, $ro);
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
		} elseif (isset($ro) && isset($lo)) {
			$ci = new \OCA\JMAPC\Store\Correlation();
			$ci->settype(CorrelationsService::TaskEntity); // Correlation Type
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
	 * @param string $uid nextcloud user id
	 * @param string $rcid local collection id
	 * @param string $reid local object id
	 *
	 * @return string what action was performed
	 */
	public function harmonizeRemoteDelete($uid, $rcid, $reid): string {

		// find correlation
		$ci = $this->CorrelationsService->findByRemoteId($uid, CorrelationsService::TaskEntity, $reid, $rcid);
		// evaluate correlation object
		if ($ci instanceof \OCA\JMAPC\Store\Correlation) {
			// destroy local entity
			$rs = $this->LocalTasksService->entityDelete($uid, $ci->getlcid(), $ci->getloid());
			// destroy correlation
			$this->CorrelationsService->delete($ci);
			// return operation status
			return 'LD';
		} else {
			// return operation status
			return 'NA';
		}

	}

	public function generateSignature($eo): string {
		
		// clone self
		$o = clone $eo;
		// remove non needed values
		unset($o->ID, $o->CID, $o->UUID, $o->RCID, $o->REID, $o->Signature, $o->CreatedOn, $o->ModifiedOn);
		// generate signature
		return md5(json_encode($o));

	}

}
