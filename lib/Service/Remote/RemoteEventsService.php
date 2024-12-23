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

use Datetime;
use DateTimeZone;
use DateInterval;
use DateTimeImmutable;
use Exception;

use JmapClient\Client;

use JmapClient\Responses\ResponseException;
use JmapClient\Responses\Calendar\EventParameters as EventParametersResponse;

use JmapClient\Requests\Calendar\CalendarGet;
use JmapClient\Requests\Calendar\CalendarSet;
use JmapClient\Requests\Calendar\CalendarQuery;
use JmapClient\Requests\Calendar\CalendarChanges;

use JmapClient\Requests\Calendar\EventGet;
use JmapClient\Requests\Calendar\EventSet;
use JmapClient\Requests\Calendar\EventQuery;
use JmapClient\Requests\Calendar\EventChanges;
use JmapClient\Requests\Calendar\EventQueryChanges;
use JmapClient\Requests\Calendar\EventParameters as EventParametersRequest;

use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\EventCollection;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\Event\EventOccurrenceObject;
use OCA\JMAPC\Objects\Event\EventAttachmentObject;
use OCA\JMAPC\Objects\Event\EventAvailabilityTypes;
use OCA\JMAPC\Objects\Event\EventLocationPhysicalObject;
use OCA\JMAPC\Objects\Event\EventLocationVirtualObject;
use OCA\JMAPC\Objects\Event\EventNotificationAnchorTypes;
use OCA\JMAPC\Objects\Event\EventNotificationObject;
use OCA\JMAPC\Objects\Event\EventNotificationPatterns;
use OCA\JMAPC\Objects\Event\EventNotificationTypes;
use OCA\JMAPC\Objects\Event\EventOccurrencePatternTypes;
use OCA\JMAPC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\JMAPC\Objects\Event\EventParticipantObject;
use OCA\JMAPC\Objects\Event\EventParticipantRoleTypes;
use OCA\JMAPC\Objects\Event\EventParticipantStatusTypes;
use OCA\JMAPC\Objects\Event\EventParticipantTypes;
use OCA\JMAPC\Objects\Event\EventSensitivityTypes;
use OCA\JMAPC\Objects\OriginTypes;

class RemoteEventsService {

	public ?DateTimeZone $SystemTimeZone = null;
	public ?DateTimeZone $UserTimeZone = null;

	protected Client $dataStore;
    protected string $dataAccount;

    protected ?string $resourceNamespace = null;
    protected ?string $resourceCollectionLabel = null;
    protected ?string $resourceEntityLabel = null;

	protected array $collectionPropertiesDefault = [];
	protected array $collectionPropertiesBasic = [];
	protected array $entityPropertiesDefault = [];
	protected array $entityPropertiesBasic = [
		'id', 'calendarIds', 'uid', 'created', 'updated'
	];

	public function __construct () {

	}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
        // evaluate if client is connected 
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
        // determine capabilities
		// FastMail Calendar uses standard capabilities name space and resource name
		/*
        if ($this->dataStore->sessionCapable('https://www.fastmail.com/dev/calendars', false)) {
            $this->resourceNamespace = 'https://www.fastmail.com/dev/calendars';
            $this->resourceCollectionLabel = 'Calendar';
            $this->resourceEntityLabel = 'CalendarEvent';
        }
		*/
        // determine account
        if ($dataAccount === null) {
            if ($this->resourceNamespace !== null) {
                $this->dataAccount = $dataStore->sessionAccountDefault($this->resourceNamespace, false);
            } else {
                $this->dataAccount = $dataStore->sessionAccountDefault('calendars');
            }
        }
        else {
            $this->dataAccount = $dataAccount;
        }

	}

	/**
     * retrieve properties for specific collection
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionFetch(string $location, string $id): ?object {
		// construct get request
		$r0 = new CalendarGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		if ($response->object(0)) {
			$ro = $response->object(0);
			return new EventCollection(
                $ro->id(),
                $ro->label(),
                $ro->priority(),
                $ro->visible(),
                $ro->color(),
            );
		} else {
			return null;
		}
    }

	/**
     * create collection in remote storage
     * 
     * @since Release 1.0.0
	 * 
	 */
	public function collectionCreate(string $location, string $label): string {
		// construct set request
		$r0 = new CalendarSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$m0 = $r0->create('1');
		$m0->label($label);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->created()['1']['id'];
    }

    /**
     * update collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionUpdate(string $location, string $id, string $label): string {
        // construct set request
		$r0 = new CalendarSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->label($label);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

    /**
     * delete collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionDelete(string $location, string $id): string {
        // construct set request
		$r0 = new CalendarSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$r0->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->deleted()[0];
    }

	/**
     * move collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionMove(string $sourceLocation, string $id, string $destinationLocation): string {
        // construct set request
		$r0 = new CalendarSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->in($destinationLocation);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

	/**
     * list of collections in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionList(?string $location = null, ?string $scope = null): array {
		// construct get request
 		$r0 = new CalendarGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set target to query request
        if ($location !== null) {
            $r0->target($location);
        }
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
        // determine if command errored
        if ($response instanceof ResponseException) {
            if ($response->type() === 'unknownMethod') {
                throw new JmapUnknownMethod($response->description(), 1);
            } else {
                throw new Exception($response->type() . ': ' . $response->description(), 1);
            }
        }
		// convert json objects to collection objects
        $list = [];
		foreach ($response->objects() as $ro) {
            $collection = new EventCollection(
                $ro->id(),
                $ro->name(),
                $ro->priority(),
                $ro->visible(),
                $ro->color(),
            );
			$list[] = $collection;
		}
		// return collection of collections
		return $list;
	}

	/**
     * search for collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionSearch(string $location, string $filter, string $scope): array {
        // construct set request
		$r0 = new CalendarQuery($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set location constraint
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// set name constraint
		if (!empty($filter)) {
			$r0->filter()->Name($filter);
		}
		// construct get request
		$r1 = new CalendarGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to collection objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Collection($message->parametersRaw());
		}
		// return collection of collections
		return $list;
    }

	/**
     * retrieve entity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityFetch(string $location, string $id, string $particulars = 'D'): EventObject|null {
		// construct set request
		$r0 = new EventGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->target($id);
		// select properties to return
		//$r0->property(...$this->defaultMailProperties);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert EventParameter object to EventObject object and return
		$eo = $this->toEventObject($response->object(0));

		return $eo;
    }
    
	/**
     * create entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityCreate(string $location, EventObject $so): EventObject|null {
		// convert entity
		$entity = $this->fromEventObject($so);
		// construct set request
		$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// add entity
		$r0->create('1', $entity)->in($location);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return entity
		if (isset($response->created()['1']['id'])) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $response->created()['1']['id'];
			$ro->CreatedOn = isset($response->created()['1']['updated']) ? new DateTimeImmutable($response->created()['1']['updated']) : null;
			$ro->ModifiedOn = $ro->CreatedOn;
			return $ro;
		} else {
			return null;
		}
    }

    /**
     * update entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityModify(string $location, string $id, EventObject $event): string|null {
		// convert entity
		$entity = $this->fromEventObject($event);
		// construct set request
		$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// add entity
		$r0->update($id, $entity)->in($location);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return entity information
		return array_key_exists($id, $response->updated()) ? (string) $id : null;
    }
    
    /**
     * delete entity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityDelete(string $location, string $id): string {
        // construct set request
		$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->deleted()[0];
    }

	/**
     * copy entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityCopy(string $sourceLocation, string $id, string $destinationLocation): string {
		return '';
    }

	/**
     * move entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityMove(string $sourceLocation, string $id, string $destinationLocation): string {
        // construct set request
		$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->in($destinationLocation);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

	/**
     * retrieve entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityList(string $location, IRange $range = null, string $sort = null, string $particulars = 'D'): array {
		// construct query request
		$r0 = new EventQuery($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set location constraint
        if (!empty($location)) {
    		$r0->filter()->in($location);
        }
		// set range constraint
		if ($range !== null) {
			if ($range->type()->value === 'absolute') {
				$r0->startAbsolute($range->getStart())->limitAbsolute($range->getCount());
			}
			if ($range->type()->value === 'relative') {
				$r0->startRelative($range->getStart())->limitRelative($range->getCount());
			}
		}
		// set sort
		if ($sort !== null) {
			match($sort) {
				'received' => $r0->sort()->received(),
				'sent' => $r0->sort()->sent(),
				'from' => $r0->sort()->from(),
				'to' => $r0->sort()->to(),
				'subject' => $r0->sort()->subject(),
				'size' => $r0->sort()->size(),
			};
		}

		// construct get request
		$r1 = new EventGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// select properties to return
        if ($particulars === 'B') {
            $r1->property(...$this->entityPropertiesBasic);
        }
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to message objects
		$state = $response->state();
		$list = $response->objects();
		// return message collection
		return ['list' => $list, 'state' => $state];
		
    }

	/**
     * search for entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entitySearch(string $location, array $filter = null, IRange $range = null, string $sort = null, string $particulars = 'D'): array {
		// construct query request
		$r0 = new EventQuery($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set location constraint
		$r0->filter()->in($location);
		// set filter constraints
		if (!empty($filter)) {
			// extract request filter
			$rf = $r0->filter();
			// iterate filter values
			foreach ($filter as $key => $value) {
				if (method_exists($rf, $key)) {
					$rf->$key($value);
				}
			}
		}
		// set range constraint
		if ($range !== null) {
			if ($range->type()->value === 'absolute') {
				$r0->startAbsolute($range->getStart())->limitAbsolute($range->getCount());
			}
			if ($range->type()->value === 'relative') {
				$r0->startRelative($range->getStart())->limitRelative($range->getCount());
			}
		}
		// set sort
		if ($sort !== null) {
			match($sort) {
				'received' => $r0->sort()->received(),
				'sent' => $r0->sort()->sent(),
				'from' => $r0->sort()->from(),
				'to' => $r0->sort()->to(),
				'subject' => $r0->sort()->subject(),
				'size' => $r0->sort()->size(),
			};
		}
		// construct get request
		$r1 = new EventGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// select properties to return
		$r1->property(...$this->defaultMailProperties);
		$r1->bodyAll(true);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to message objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Message($message->parametersRaw());
		}
		// return message collection
		return $list;
    }

	/**
     * delta of changes for collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityDelta(string $location, string $state, string $particulars = 'D'): array {
        // construct set request
		$r0 = new EventQueryChanges($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set location constraint
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// set state constraint
		if (!empty($state)) {
			$r0->state($state);
		} else {
			$r0->state('0');
		}
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command errored
        if ($response instanceof ResponseException) {
            if ($response->type() === 'unknownMethod') {
                throw new JmapUnknownMethod($response->description(), 1);
            } else {
                throw new Exception($response->type() . ': ' . $response->description(), 1);
            }
        }
		// convert json objects to collection objects
		$list = $response->objects();
		// return collection of collections
		return $list;
    }

	/**
     * retrieve collection entity attachment from remote storage
     * 
     * @since Release 1.0.0
     * 
     * @param array $batch		Batch of Attachment ID's
	 * 
	 * @return array
	 */
	public function fetchAttachment(array $batch): array {

		// check to for entries in batch collection
        if (count($batch) == 0) {
            return array();
        }
		// retrieve attachments
		$rs = $this->RemoteCommonService->fetchAttachment($this->DataStore, $batch);
		// construct response collection place holder
		$rc = array();
		// check for response
		if (isset($rs)) {
			// process collection of objects
			foreach($rs as $entry) {
				if (!isset($entry->ContentType) || $entry->ContentType == 'application/octet-stream') {
					$type = \OCA\JMAPC\Utile\MIME::fromFileName($entry->Name);
				} else {
					$type = $entry->ContentType;
				}
				// insert attachment object in response collection
				$rc[] = new EventAttachmentObject(
					'D',
					$entry->AttachmentId->Id, 
					$entry->Name,
					$type,
					'B',
					$entry->Size,
					$entry->Content
				);
			}
		}
		// return response collection
		return $rc;

    }

    /**
     * create collection item attachment in local storage
     * 
     * @since Release 1.0.0
     * 
	 * @param string $aid - Affiliation ID
     * @param array $sc - Collection of EventAttachmentObject(S)
	 * 
	 * @return string
	 */
	public function createAttachment(string $aid, array $batch): array {

		// check to for entries in batch collection
        if (count($batch) == 0) {
            return array();
        }
		// construct command collection place holder
		$cc = array();
		// process batch
		foreach ($batch as $key => $entry) {
			// construct command object
			$eo = new \OCA\JMAPC\Utile\Eas\Type\FileAttachmentType();
			$eo->IsInline = false;
			$eo->IsContactPhoto = false;
			$eo->Name = $entry->Name;
			$eo->ContentId = $entry->Name;
			$eo->ContentType = $entry->Type;
			$eo->Size = $entry->Size;
			
			switch ($entry->Encoding) {
				case 'B':
					$eo->Content = $entry->Data;
					break;
				case 'B64':
					$eo->Content = base64_decode($entry->Data);
					break;
			}
			// insert command object in to collection
			$cc[] = $eo;
		}
		// execute command(s)
		$rs = $this->RemoteCommonService->createAttachment($this->DataStore, $aid, $cc);
		// construct results collection place holder
		$rc = array();
		// check for response
		if (isset($rs)) {
			// process collection of objects
			foreach($rs as $key => $entry) {
				$ro = clone $batch[$key];
				$ro->Id = $entry->AttachmentId->Id;
				$ro->Data = null;
				$ro->AffiliateId = $entry->AttachmentId->RootItemId;
				$ro->AffiliateState = $entry->AttachmentId->RootItemChangeKey;
				$rc[] = $ro;
			}

        }
		// return response collection
		return $rc;
    }

    /**
     * delete collection item attachment from local storage
     * 
     * @since Release 1.0.0
     * 
     * @param string $aid - Attachment ID
	 * 
	 * @return bool true - successfully delete / False - failed to delete
	 */
	public function deleteAttachment(array $batch): array {

		// check to for entries in batch collection
        if (count($batch) == 0) {
            return array();
        }
		// execute command
		$data = $this->RemoteCommonService->deleteAttachment($this->DataStore, $batch);

		return $data;

    }

	/**
     * convert remote object to local object
     * 
     * @since Release 1.0.0
     * 
	 * @param EventParametersResponse $so	remote entity object
	 * 
	 * @return EventObject			local entity object
	 */
	public function toEventObject(EventParametersResponse $so): EventObject {
		// create object
		$eo = new EventObject();
		// source origin
		$eo->Origin = OriginTypes::External;
        // id
        if ($so->id()){
            $eo->ID = $so->id();
        }
		// universal id
        if ($so->uid()){
            $eo->UUID = $so->uid();
        }
		// creation date time
        if ($so->created()){
            $eo->CreatedOn = $so->created();
        }
		// modification date time
		if ($so->updated()){
            $eo->ModifiedOn = $so->updated();
        }
		// sequence
        if ($so->sequence()){
            $eo->Sequence = $so->sequence();
        }
		// time zone
        if ($so->timezone()) {
        	$eo->TimeZone = new DateTimeZone($so->timezone());
        }
		// start date/time
		if ($so->starts()) {
			$eo->StartsOn = $so->starts();
			$eo->StartsTZ = $eo->TimeZone;
		}
		// end date/time
        if ($so->ends()) {
            $eo->EndsOn = $so->ends();
			$eo->EndsTZ = $eo->TimeZone;
        }
		// duration
		if ($so->duration()) {
			$eo->Duration = $so->duration();
		}
		// all bay event
		if($so->timeless()) {
			$eo->Timeless = true;
		}
		// label
        if ($so->label()) {
            $eo->Label = $so->label();
        }
		// description
		if ($so->descriptionContents()) {
			$eo->Description = $so->descriptionContents();
		}
		// physical location(s)
		foreach ($so->physicalLocations() as $id => $entry) {
			$location = new EventLocationPhysicalObject();
			$location->Id  = $id;
			$location->Name = $entry->label();
			$location->Description = $entry->description();
			$eo->LocationsPhysical[$id] = $location;
		}
		// virtual location(s)
		foreach ($so->virtualLocations() as $id => $entry) {
			$location = new EventLocationVirtualObject();
			$location->Id  = $id;
			$location->Name = $entry->label();
			$location->Description = $entry->description();
			$eo->LocationsVirtual[$id] = $location;
		}
		// availability
		if ($so->availability()) {
			$eo->Availability = match (strtolower($so->availability())) {
				'free' => EventAvailabilityTypes::Free,
				default => EventAvailabilityTypes::Busy,
			};
		}
		// priority
		if ($so->priority()) {
			$eo->Priority = $so->priority();
		}
		// sensitivity
		if ($so->privacy()) {
			$eo->Sensitivity = match (strtolower($so->privacy())) {
				'private' => EventSensitivityTypes::Private,
				'secret' => EventSensitivityTypes::Secret,
				default => EventSensitivityTypes::Public,
			};
		}
		// color
		if ($so->color()) {
			$eo->Color = $so->color();
		}
		// categories(s)
		foreach ($so->categories() as $id => $entry) {
			$eo->Categories[] = $entry;
		}
		// tag(s)
		foreach ($so->tags() as $id => $entry) {
			$eo->Tags[] = $entry;
		}
		// Organizer - Address and Name
		if ($so->sender()) {
			$sender = $this->fromSender($so->sender());
			$eo->Organizer->Address = $sender['address'];
			$eo->Organizer->Name = $sender['name'];
		}
		// participant(s)
		foreach ($so->participants() as $id => $entry) {
			$participant = new EventParticipantObject();
			$participant->id = $id;
			$participant->address = $entry->address();
			$participant->name = $entry->name();
			$participant->description = $entry->description();
			$participant->comment = $entry->comment();
			$participant->type = match (strtolower($entry->kind())) {
				'individual' => EventParticipantTypes::Individual,
				'group' => EventParticipantTypes::Group,
				'resource' => EventParticipantTypes::Resource,
				'location' => EventParticipantTypes::Location,
				default => EventParticipantTypes::Unknown,
			};
			$participant->status = match (strtolower($entry->status())) {
				'accepted' => EventParticipantStatusTypes::Accepted,
				'declined' => EventParticipantStatusTypes::Declined,
				'tentative' => EventParticipantStatusTypes::Tentative,
				'delegated' => EventParticipantStatusTypes::Delegated,
				default => EventParticipantStatusTypes::None,
			};
			
			foreach ($entry->roles() as $role => $value) {
				$participant->roles[$role] = EventParticipantRoleTypes::from($role);
			}
			$eo->Participants[$id] = $participant;
		}
		// notification(s)
		foreach ($so->notifications() as $id => $entry) {
			$trigger = $entry->trigger();
			$notification = new EventNotificationObject();
			$notification->Type = match (strtolower($entry->type())) {
				'email' => EventNotificationTypes::Email,
				default => EventNotificationTypes::Visual,
			};
			$notification->Pattern = match (strtolower($trigger->type())) {
				'absolute' => EventNotificationPatterns::Absolute,
				'relative' => EventNotificationPatterns::Relative,
				default => EventNotificationPatterns::Unknown,
			};
			if ($notification->Pattern === EventNotificationPatterns::Absolute) {
				$notification->When = $trigger->when();
			} elseif ($notification->Pattern === EventNotificationPatterns::Relative) {
				$notification->Anchor = match (strtolower($trigger->anchor())) {
					'end' => EventNotificationAnchorTypes::End,
					default => EventNotificationAnchorTypes::Start,
				};
				$notification->Offset = $trigger->offset();
			}
			$eo->Notifications[$id] = $notification;
		}
		// occurrence(s)
		foreach ($so->recurrenceRules() as $id => $entry) {
			$occurrence = new EventOccurrenceObject();
			
			// Interval
			if ($entry->interval() !== null) {
				$occurrence->Interval = $entry->interval();
			}
			// Iterations
			if ($entry->count() !== null) {
				$occurrence->Iterations = $entry->count();
			}
			// Conclusion
			if ($entry->until() !== null) {
				$occurrence->Concludes = new DateTime($entry->until());
			}
			// Daily
			if ($entry->frequency() === 'daily') {
				$occurrence->Pattern = EventOccurrencePatternTypes::Absolute;
				$occurrence->Precision = EventOccurrencePrecisionTypes::Daily;
            }
			// Weekly
			if ($entry->frequency() === 'weekly') {
				$occurrence->Pattern = EventOccurrencePatternTypes::Absolute;
				$occurrence->Precision = EventOccurrencePrecisionTypes::Weekly;
				$occurrence->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
            }
			// Monthly 
			if ($entry->frequency() === 'monthly') {
				$occurrence->Precision = EventOccurrencePrecisionTypes::Monthly;
				// Absolute
				if (count($entry->byDayOfMonth())) {
					$occurrence->Pattern = EventOccurrencePatternTypes::Absolute;
					$occurrence->OnDayOfMonth = $entry->byDayOfMonth();
				}
				// Relative
				else {
					$occurrence->Pattern = EventOccurrencePatternTypes::Relative;
					$occurrence->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
				}
            }
			// Yearly
			if ($entry->frequency() === 'yearly') {
				$occurrence->Precision = EventOccurrencePrecisionTypes::Yearly;
				// nth day of year
				if (count($entry->byDayOfYear())) {
					$occurrence->Pattern = EventOccurrencePatternTypes::Absolute;
					$occurrence->OnDayOfYear = $entry->byDayOfYear();
					$occurrence->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
				}
				// nth week of year
				elseif (count($entry->byWeekOfYear())) {
					$occurrence->Pattern = EventOccurrencePatternTypes::Relative;
					$occurrence->OnWeekOfYear = $entry->byWeekOfYear();
					$occurrence->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
				}
				// nth month of year
				elseif (count($entry->byMonthOfYear())) {
					if (count($entry->byDayOfMonth())) {
						$occurrence->Pattern = EventOccurrencePatternTypes::Absolute;
						$occurrence->OnDayOfMonth = $entry->byDayOfMonth();
					} else {
						$occurrence->Pattern = EventOccurrencePatternTypes::Relative;
						$occurrence->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
					}
				}
			}
			// add to collection
			$eo->OccurrencePatterns[] = $occurrence;
		}
		
		return $eo;

    }

	/**
     * convert remote EventObject to remote EasObject
     * 
     * @since Release 1.0.0
     * 
	 * @param EventObject $so
	 * 
	 * @return EventParametersRequest
	 */
	public function fromEventObject(EventObject $eo): EventParametersRequest {

		// create object
		$to = new EventParametersRequest();
		// universal id
        if ($eo->UUID){
            $to->uid($eo->UUID);
        }
		// creation date time
        if ($eo->CreatedOn){
        	$to->created($eo->CreatedOn);
        }
		// modification date time
		if ($eo->ModifiedOn){
    		$to->updated($eo->ModifiedOn);
        }
		// sequence
		if ($eo->Sequence){
    		$to->sequence($eo->Sequence);
        }
		// time zone
		if ($eo->TimeZone){
    		$to->timezone($eo->TimeZone->getName());
        }
		// start date/time
		if ($eo->StartsOn){
    		$to->starts($eo->StartsOn);
        }
		// duration
		if ($eo->Duration){
    		$to->duration($eo->Duration);
        } else {
			$to->duration($eo->StartsOn->diff($eo->EndsOn));
		}
		// all day Event
		if ($eo->Timeless){
    		$to->timeless($eo->Timeless);
        }
		// label
        if ($eo->Label){
    		$to->label($eo->Label);
        }
		// description
        if ($eo->Description){
    		$to->descriptionContents($eo->Description);
        }
		// physical location(s)
        foreach ($eo->LocationsPhysical as $entry) {
			$location = $to->physicalLocations($entry->id);
			if ($entry->name) {
				$location->label($entry->name);
			}
			if ($entry->description) {
				$location->description($entry->description);
			}
		}
		// virtual location(s)
        foreach ($eo->LocationsVirtual as $entry) {
			$location = $to->virtualLocations($entry->id);
			if ($entry->name) {
				$location->label($entry->name);
			}
			if ($entry->description) {
				$location->description($entry->description);
			}
		}
		// availability
        if ($eo->Availability){
    		$to->availability(match ($eo->Availability) {
				EventAvailabilityTypes::Free => 'free',
				default => 'busy',
			});
        }
		// priority
        if ($eo->Priority){
    		$to->priority($eo->Priority);
        }
		// sensitivity
        if ($eo->Sensitivity){
    		$to->privacy(match ($eo->Sensitivity) {
				EventSensitivityTypes::Private => 'private',
				EventSensitivityTypes::Secret => 'secret',
				default => 'public',
			});
        }
		// color
        if ($eo->Color){
    		$to->color($eo->Color);
        }
		// categories(s)
        if (!empty($eo->Categories)){
    		$to->categories(...$eo->Categories);
        }
		// tag(s)
        if (!empty($eo->Tags)){
    		$to->tags(...$eo->Tags);
        }
		// participant(s)
        foreach ($eo->Participants as $entry) {
			$participant = $to->participants($entry->id);
			if ($entry->address) {
				$participant->address($entry->address);
				$participant->send('imip', 'mailto:' . $entry->address);
			}
			if ($entry->name) {
				$participant->name($entry->name);
			}
			if ($entry->description) {
				$participant->description($entry->description);
			}
			if ($entry->comment) {
				$participant->comment($entry->comment);
			}
			if ($entry->type) {
				$participant->kind(match ($entry->type) {
					EventParticipantTypes::Individual => 'group',
					EventParticipantTypes::Individual => 'resource',
					EventParticipantTypes::Individual => 'location',
					default => 'individual',
				});
			}
			if ($entry->status) {
				$participant->kind(match ($entry->status) {
					EventParticipantStatusTypes::Accepted => 'accepted',
					EventParticipantStatusTypes::Declined => 'declined',
					EventParticipantStatusTypes::Tentative => 'tentative',
					EventParticipantStatusTypes::Delegated => 'delegated',
					default => 'needs-action',
				});
			}
			if (!empty($entry->roles)) {
				$roles = [];
				foreach ($entry->roles as $role) {
					$roles[] = $role->value;
				}
				$participant->roles(...$roles);
			}
		}
		// notification(s)
        foreach ($eo->Notifications as $entry) {
			$notification = $to->notifications($entry->id);
			if ($entry->Type) {
				$notification->type(match ($entry->type) {
					EventNotificationTypes::Email => 'email',
					default => 'display',
				});
			}
			if ($entry->Pattern === EventNotificationPatterns::Absolute) {
				$notification->trigger('absolute')->when($entry->When);
			} elseif ($entry->Pattern === EventNotificationPatterns::Relative) {
				if ($entry->Anchor === EventNotificationAnchorTypes::End) {
					$notification->trigger('offset')->anchor('end')->offset($entry->Offset);
				} else {
					$notification->trigger('offset')->anchor('start')->offset($entry->Offset);
				}
			} else {
				$notification->trigger('unknown');
			}
		}
		// occurrence(s)
        foreach ($eo->OccurrencePatterns as $id => $entry) {
			$pattern = $to->recurrenceRules($id);
			if ($entry->Precision) {
				$pattern->frequency(match ($entry->Precision) {
					EventOccurrencePrecisionTypes::Yearly => 'yearly',
					EventOccurrencePrecisionTypes::Monthly => 'monthly',
					EventOccurrencePrecisionTypes::Weekly => 'weekly',
					EventOccurrencePrecisionTypes::Daily => 'daily',
					EventOccurrencePrecisionTypes::Hourly => 'hourly',
					EventOccurrencePrecisionTypes::Minutely => 'minutely',
					EventOccurrencePrecisionTypes::Secondly => 'secondly',
					default => 'daily',
				});
			}
			if ($entry->Interval) {
				$pattern->interval($entry->Interval);
			}
			if ($entry->Iterations) {
				$pattern->count($entry->Iterations);
			}
			if ($entry->Concludes) {
				$pattern->until($entry->Concludes);
			}
			if ($entry->OnDayOfWeek !== []){
				foreach ($entry->OnDayOfWeek as $id => $day) {
					$nDay = $pattern->byDayOfWeek($id);
					$nDay->day($day);
				}	
			}
			if (!empty($entry->OnDayOfMonth)){
				$pattern->byDayOfMonth(...$entry->OnDayOfMonth);
			}
			if (!empty($entry->OnDayOfYear)){
				$pattern->byDayOfYear(...$entry->OnDayOfYear);
			}
			if (!empty($entry->OnWeekOfMonth)){
				$pattern->byWeekOfYear(...$entry->OnWeekOfMonth);
			}
			if (!empty($entry->OnWeekOfYear)){
				$pattern->byWeekOfYear(...$entry->OnWeekOfYear);
			}
			if (!empty($entry->OnMonthOfYear)){
				$pattern->byMonthOfYear(...$entry->OnMonthOfYear);
			}
			if (!empty($entry->OnHour)){
				$pattern->byHour(...$entry->OnHour);
			}
			if (!empty($entry->OnMinute)){
				$pattern->byMinute(...$entry->OnMinute);
			}
			if (!empty($entry->OnSecond)){
				$pattern->bySecond(...$entry->OnSecond);
			}
			if (!empty($entry->OnPosition)){
				$pattern->byPosition(...$entry->OnPosition);
			}
		}
        
		return $to;

    }

	
    public function generateSignature(EventObject $eo): string {
        
        // clone self
        $o = clone $eo;
        // remove non needed values
        unset($o->Origin, $o->ID, $o->UUID, $o->Signature, $o->CCID, $o->CEID, $o->CESN, $o->CreatedOn, $o->ModifiedOn);
        // generate signature
        return md5(json_encode($o));

    }

	/**
     * convert remote availability status to event object availability status
	 * 
     * @since Release 1.0.0
     * 
	 * @param string $value		remote availability status value
	 * 
	 * @return string			event object availability status value
	 */
	private function fromSender(?string $value): array {
		
		// Check if there are angle brackets
		$bracketStart = strpos($value, '<');
		$bracketEnd = strpos($value, '>');

		// If brackets are present
		if ($bracketStart !== false && $bracketEnd !== false) {
			// Extract the name and address
			$name = trim(substr($value, 0, $bracketStart));
			$address = trim(substr($value, $bracketStart + 1, $bracketEnd - $bracketStart - 1));
		} else {
			$name = null;
			$address = $value;
		}

		return ['address' => $address, 'name' => $name];
		
	}

	/**
     * convert event object availability status to remote availability status
	 * 
     * @since Release 1.0.0
     * 
	 * @param string $value		event object availability status value
	 * 
	 * @return string	 		remote availability status value
	 */
	private function toSender(string $address, ?string $name): string {
		
		// transposition matrix
		$_tm = array(
			'F' => 'free',
			'B' => 'busy',
		);
		// evaluate if value exists
		if (isset($_tm[$value])) {
			// return transposed value
			return $_tm[$value];
		} else {
			// return default value
			return 'busy';
		}

	}

	/**
     * convert remote days of the week to event object days of the week
	 * 
     * @since Release 1.0.0
     * 
	 * @param array $days - remote days of the week values(s)
	 * 
	 * @return array event object days of the week values(s)
	 */
	private function fromDaysOfWeek(array $days): array {

		$dow = [];
		foreach ($days as $entry) {
			if (isset($entry['day'])) {
				$dow[] = $entry['day'];
			}
		}
		return $dow;

	}

	/**
     * convert event object days of the week to remote days of the week
	 * 
     * @since Release 1.0.0
     * 
	 * @param array $days - internal days of the week values(s)
	 * 
	 * @return array event object days of the week values(s)
	 */
	private function toDaysOfWeek(array $days): array {

		$dow = [];
		foreach ($days as $key => $value) {
			# code...
		}

		return $dow;

	}

}
