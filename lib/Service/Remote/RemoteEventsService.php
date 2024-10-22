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
use Exception;

use JmapClient\Client;

use JmapClient\Responses\Calendar\EventParameters as EventParametersResponse;

use JmapClient\Responses\ResponseException;
use JmapClient\Requests\Calendar\CalendarGet;
use JmapClient\Requests\Calendar\CalendarSet;
use JmapClient\Requests\Calendar\CalendarQuery;
use JmapClient\Requests\Calendar\CalendarChanges;

use JmapClient\Requests\Calendar\EventGet;
use JmapClient\Requests\Calendar\EventSet;
use JmapClient\Requests\Calendar\EventQuery;
use JmapClient\Requests\Calendar\EventChanges;
use JmapClient\Requests\Calendar\EventQueryChanges;
use JmapClient\Requests\Calendar\EventParameters;

use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\EventCollectionObject;
use OCA\JMAPC\Objects\EventObject;
use OCA\JMAPC\Objects\EventAttachmentObject;
use OCA\JMAPC\Providers\Calendar\EventCollection;

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
			return new EventCollectionObject(
                $ro->id(),
                $ro->name(),
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
		$m0->in($location);
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
            $collection = new EventCollectionObject(
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
	public function entityCreate(string $location, IMessage $message): string {
		// construct set request
		$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->create('1')->parametersRaw($message->getParameters())->in($location);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->created()['1']['id'];
    }

    /**
     * update entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityModify(string $location, string $id, IMessage $message): string {
		//
		//TODO: Replace this code with an actual property update instead of replacement
		//
		// construct set request
		//$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		//$r0->create('1')->parametersRaw($message->getParameters())->in($location);
		// construct set request
		//$r1 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		//$r1->delete($id);
		// construct set request
		$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$messageData = $message->getParameters();
		$messageData['id'] = $id;
		$r0->update($id)->parametersRaw($messageData);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
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
        	//$r0->filter()->in($location);
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
	 * @param EventParameters $so	remote entity object
	 * 
	 * @return EventObject			local entity object
	 */
	public function toEventObject(EventParametersResponse $so): EventObject {
		// create object
		$eo = new EventObject();
		// source origin
		$eo->Origin = 'R';
        // id
        if ($so->id()){
            $eo->ID = $so->id();
        }
		// universal id
        if ($so->uid()){
            $eo->UUID = $so->uid();
        }
		// creation date
        if ($so->created()){
            $eo->CreatedOn = $so->created();
        }
		// modification date
		if ($so->updated()){
            $eo->ModifiedOn = $so->updated();
        }
		// time zone
        if ($so->timezone()) {
        	$eo->TimeZone = new DateTimeZone($so->timezone());
        }
		// Start Date/Time
		if ($so->starts()) {
			$eo->StartsOn = $so->starts();
			$eo->StartsTZ = $eo->TimeZone;
		}
		// End Date/Time
        if ($so->ends()) {
            $eo->EndsOn = $so->ends();
			$eo->EndsTZ = $eo->TimeZone;
        }
		// All Day Event
		if($so->timeless() && $so->timeless() === true) {
			$eo->StartsOn->setTime(0,0,0,0);
			$eo->EndsOn->setTime(0,0,0,0);
		}
		// Label
        if ($so->label()) {
            $eo->Label = $so->label();
        }
		// Description
		if ($so->descriptionContents()) {
			$eo->Notes = $so->descriptionContents();
		}
		// Location
		/*
		if (!empty($so->Location)) {
			$eo->Location = $so->Location->DisplayName->getContents();
		}
		*/
		// Availability
		if ($so->availability()) {
			$eo->Availability = $this->fromAvailability($so->availability());
		}
		// Sensitivity
		if ($so->privacy()) {
			$eo->Sensitivity = $this->fromSensitivity($so->privacy());
		}
		// Tag(s)
		/*
        if ($so->categories()) {
            if (!is_array($so->Categories->Category)) {
                $so->Categories->Category = [$so->Categories->Category];
            }
			foreach($so->Categories->Category as $entry) {
				$eo->addTag($entry->getContents());
			}
        }
		*/
		// Organizer - Address and Name
		if ($so->sender()) {
			$sender = $this->fromSender($so->sender());
			$eo->Organizer->Address = $sender['address'];
			$eo->Organizer->Name = $sender['name'];
		}
		/*
		// Attendee(s)
		if (isset($so->Attendees->Attendee)) {
			foreach($so->Attendees->Attendee as $entry) {
				if ($entry->Email) {
					$a = $entry->Email->getContents();
					// evaluate, if name exists
					$n = (isset($entry->Name)) ? $entry->Name->getContents() : null;
					// evaluate, if type exists
					$t = (isset($entry->AttendeeType)) ? $this->fromAttendeeType($entry->AttendeeType->getContents()) : null;
					// evaluate, if status exists
					$s = (isset($entry->AttendeeStatus)) ? $this->fromAttendeeStatus($entry->AttendeeStatus->getContents()) : null;
					// add attendee
					$eo->addAttendee($a, $n, $t, $s);
				}
			}
			unset($a, $n, $t, $s);
		}
		// Notification(s)
		if (isset($so->Reminder)) { 
			$w = new DateInterval('PT' . $so->Reminder->getContents() . 'M');
			$w->invert = 1;
			$eo->addNotification(
				'D',
				'R',
				$w
			);
		}
		// Occurrence
        if (isset($so->Recurrence)) {
			// Interval
			if (isset($so->Recurrence->Interval)) {
				$eo->Occurrence->Interval = $so->Recurrence->Interval->getContents();
			}
			// Iterations
			if (isset($so->Recurrence->Occurrences)) {
				$eo->Occurrence->Iterations = $so->Recurrence->Occurrences->getContents();
			}
			// Conclusion
			if (isset($so->Recurrence->Until)) {
				$eo->Occurrence->Concludes = new DateTime($so->Recurrence->Until->getContents());
			}
			// Daily
			if ($so->Recurrence->Type->getContents() == '0') {

				$eo->Occurrence->Pattern = 'A';
				$eo->Occurrence->Precision = 'D';

            }
			// Weekly
			if ($so->Recurrence->Type->getContents() == '1') {
				
				$eo->Occurrence->Pattern = 'A';
				$eo->Occurrence->Precision = 'W';
				
				if (isset($so->Recurrence->DayOfWeek)) {
					$eo->Occurrence->OnDayOfWeek = $this->fromDaysOfWeek((int) $so->Recurrence->DayOfWeek->getContents(), true);
				}

            }
			// Monthly Absolute
			if ($so->Recurrence->Type->getContents() == '2') {
				
				$eo->Occurrence->Pattern = 'A';
				$eo->Occurrence->Precision = 'M';
				
				if (isset($so->Recurrence->DayOfMonth)) {
					$eo->Occurrence->OnDayOfMonth = $this->fromDaysOfMonth($so->Recurrence->DayOfMonth->getContents());
				}

            }
			// Monthly Relative
			if ($so->Recurrence->Type->getContents() == '3') {
				
				$eo->Occurrence->Pattern = 'R';
				$eo->Occurrence->Precision = 'M';
				
				if (isset($so->Recurrence->DayOfWeek)) {
					$eo->Occurrence->OnDayOfWeek = $this->fromDaysOfWeek((int) $so->Recurrence->DayOfWeek->getContents(), true);
				}
				if (isset($so->Recurrence->WeekOfMonth)) {
					$eo->Occurrence->OnWeekOfMonth = $this->fromWeekOfMonth($so->Recurrence->WeekOfMonth->getContents());
				}

            }
			// Yearly Absolute
			if ($so->Recurrence->Type->getContents() == '5') {
				
				$eo->Occurrence->Pattern = 'A';
				$eo->Occurrence->Precision = 'Y';
				
				if (isset($so->Recurrence->DayOfMonth)) {
					$eo->Occurrence->OnDayOfMonth = $this->fromDaysOfMonth($so->Recurrence->DayOfMonth->getContents());
				}
				if (isset($so->Recurrence->MonthOfYear)) {
					$eo->Occurrence->OnMonthOfYear = $this->fromMonthOfYear($so->Recurrence->MonthOfYear->getContents());
				}

            }
			// Yearly Relative
			if ($so->Recurrence->Type->getContents() == '6') {
				
				$eo->Occurrence->Pattern = 'R';
				$eo->Occurrence->Precision = 'Y';
				
				if (isset($so->Recurrence->DayOfWeek)) {
					$eo->Occurrence->OnDayOfWeek = $this->fromDaysOfWeek($so->Recurrence->DayOfWeek->getContents(), true);
				}
				if (isset($so->Recurrence->WeekOfMonth)) {
					$eo->Occurrence->OnWeekOfMonth = $this->fromWeekOfMonth($so->Recurrence->WeekOfMonth->getContents());
				}
				if (isset($so->Recurrence->MonthOfYear)) {
					$eo->Occurrence->OnMonthOfYear = $this->fromMonthOfYear($so->Recurrence->MonthOfYear->getContents());
				}

            }
			// Excludes
			if (isset($so->DeletedOccurrences)) {
				foreach($so->DeletedOccurrences->DeletedOccurrence as $entry) {
					if (isset($entry->Start)) {
						$o->Occurrence->Excludes[] = new DateTime($entry->Start);
					}
				}
			}
        }
        // Attachment(s)
		if (isset($so->Attachments)) {
			// evaluate if property is a collection
			if (!is_array($so->Attachments->Attachment)) {
				$so->Attachments->Attachment = [$so->Attachments->Attachment];
			}
			foreach($so->Attachments->Attachment as $entry) {
				$type = \OCA\JMAPC\Utile\MIME::fromFileName($entry->DisplayName->getContents());
				$eo->addAttachment(
					'D',
					$entry->FileReference->getContents(), 
					$entry->DisplayName->getContents(),
					$type,
					'B',
					$entry->EstimatedDataSize->getContents()
				);
			}
		}
		*/

		return $eo;

    }

	/**
     * convert remote EventObject to remote EasObject
     * 
     * @since Release 1.0.0
     * 
	 * @param EventObject $so		entity as EventObject
	 * 
	 * @return EasObject            entity as EasObject
	 */
	public function fromEventObject(EventObject $so): EasObject {

		// create object
		$eo = new EasObject('AirSync');
		// Time Zone
        if (!empty($so->TimeZone)) {
        	$eo->Timezone = new EasProperty('Calendar', $this->toTimeZone($so->TimeZone, $so->StartsOn));
			//$eo->Timezone = new EasProperty('Calendar', 'LAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAAAABAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAACAAIAAAAAAAAAxP///w==');
        }
		elseif (!empty($so->StartsTZ)) {
			$eo->Timezone = new EasProperty('Calendar', $this->toTimeZone($so->StartsTZ, $so->StartsOn));
			//$eo->Timezone = new EasProperty('Calendar', 'LAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAAAABAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAACAAIAAAAAAAAAxP///w==');
		}
		// Start Date/Time
		if (!empty($so->StartsOn)) {
			$dt = (clone $so->StartsOn)->setTimezone(new DateTimeZone('UTC'));
			$eo->StartTime = new EasProperty('Calendar', $dt->format('Ymd\\THisp')); // YYYYMMDDTHHMMSSZ
		}
		// End Date/Time
		if (!empty($so->EndsOn)) {
			$dt = (clone $so->EndsOn)->setTimezone(new DateTimeZone('UTC'));
			$eo->EndTime = new EasProperty('Calendar', $dt->format('Ymd\\THisp')); // YYYYMMDDTHHMMSSZ
		}
		// All Day Event
		if((fmod(($so->EndsOn->getTimestamp() - $so->StartsOn->getTimestamp()), 86400) == 0)) {
			$eo->AllDayEvent = new EasProperty('Calendar', 1);
		}
		else {
			$eo->AllDayEvent = new EasProperty('Calendar', 0);
		}
		// Label
        if (!empty($so->Label)) {
            $eo->Subject = new EasProperty('Calendar', $so->Label);
        }
		// Sensitivity
        if (!empty($so->Sensitivity)) {
            $eo->Sensitivity = new EasProperty('Calendar', $this->toSensitivity($so->Sensitivity));
        }
		else {
			$eo->Sensitivity = new EasProperty('Calendar', '2');
		}

		// Notes
        if (!empty($so->Notes)) {
            $eo->Body = new EasObject('AirSyncBase');
            $eo->Body->Type = new EasProperty('AirSyncBase', EasTypes::BODY_TYPE_TEXT);
            //$eo->Body->EstimatedDataSize = new EasProperty('AirSyncBase', strlen($so->Notes));
            $eo->Body->Data = new EasProperty('AirSyncBase', $so->Notes);
        }
		else {
			$eo->Body = new EasObject('AirSyncBase');
            $eo->Body->Type = new EasProperty('AirSyncBase', EasTypes::BODY_TYPE_TEXT);
            $eo->Body->Data = new EasProperty('AirSyncBase', ' ');
		}
		
		// Location
        if (!empty($so->Location)) {
            $eo->Location = new EasProperty('AirSyncBase', $so->Location);
        }
		// Availability
        if (!empty($so->Availability)) {
            $eo->BusyStatus = new EasProperty('Calendar', $this->toAvailability($so->Availability));
        }
		else {
			$eo->BusyStatus = new EasProperty('Calendar', 2);
		}
		// Notifications
        if (count($so->Notifications) > 0) {
			$eo->Reminder = new \OCA\JMAPC\Utile\Eas\EasProperty('Calendar', 10);
        }
		else {
			$eo->Reminder = new \OCA\JMAPC\Utile\Eas\EasProperty('Calendar', 0);
		}
		// MeetingStatus
		$eo->MeetingStatus = new \OCA\JMAPC\Utile\Eas\EasProperty('Calendar', 0);

		// Tag(s)
        if (count($so->Tags) > 0) {
            $eo->Categories = new EasObject('Calendar');
            $eo->Categories->Category = new EasCollection('Calendar');
            foreach($so->Tags as $entry) {
                $eo->Categories->Category[] = new EasProperty('Calendar', $entry);
            }
        }
		

		
		// Occurrence
		if (isset($so->Occurrence) && !empty($so->Occurrence->Precision)) {
			$eo->Recurrence = new EasObject('Calendar');
			// Occurrence Interval
			if (isset($so->Occurrence->Interval)) {
				$eo->Recurrence->Interval = new EasProperty('Calendar', $so->Occurrence->Interval);
			}
			// Occurrence Iterations
			if (!empty($so->Occurrence->Iterations)) {
				$eo->Recurrence->Occurrences = new EasProperty('Calendar', $so->Occurrence->Iterations);
			}
			// Occurrence Conclusion
			if (!empty($so->Occurrence->Concludes)) {
				$eo->Recurrence->Until = new EasProperty('Calendar', $so->Occurrence->Concludes->format('Ymd\\THis')); // YYYY-MM-DDTHH:MM:SS.MSSZ);
			}
			// Based on Precision
			// Occurrence Daily
			if ($so->Occurrence->Precision == 'D') {
				$eo->Recurrence->Type = new EasProperty('Calendar', 0);
			}
			// Occurrence Weekly
			elseif ($so->Occurrence->Precision == 'W') {
				$eo->Recurrence->Type = new EasProperty('Calendar', 1);
				$eo->Recurrence->DayOfWeek = new EasProperty('Calendar', $this->toDaysOfWeek($so->Occurrence->OnDayOfWeek));
			}
			// Occurrence Monthly
			elseif ($so->Occurrence->Precision == 'M') {
				if ($so->Occurrence->Pattern == 'A') {
					$eo->Recurrence->Type = new EasProperty('Calendar', 2);
					$eo->Recurrence->DayOfMonth = new EasProperty('Calendar', $this->toDaysOfMonth($so->Occurrence->OnDayOfMonth));
				}
				elseif ($so->Occurrence->Pattern == 'R') {
					$eo->Recurrence->Type = new EasProperty('Calendar', 3);
					$eo->Recurrence->DayOfWeek = new EasProperty('Calendar', $this->toDaysOfWeek($so->Occurrence->OnDayOfWeek));
					$eo->Recurrence->DayOfMonth = new EasProperty('Calendar', $this->toDaysOfMonth($so->Occurrence->OnDayOfMonth));
				}
			}
			// Occurrence Yearly
			elseif ($so->Occurrence->Precision == 'Y') {
				if ($so->Occurrence->Pattern == 'A') {
					$eo->Recurrence->Type = new EasProperty('Calendar', 5);
					$eo->Recurrence->DayOfMonth = new EasProperty('Calendar', $this->toDaysOfMonth($so->Occurrence->OnDayOfMonth));
					$eo->Recurrence->MonthOfYear = new EasProperty('Calendar', $this->toMonthOfYear($so->Occurrence->OnMonthOfYear));
				}
				elseif ($so->Occurrence->Pattern == 'R') {
					$eo->Recurrence->Type = new EasProperty('Calendar', 6);
					$eo->Recurrence->DayOfWeek = new EasProperty('Calendar', $this->toDaysOfWeek($so->Occurrence->OnDayOfWeek));
					$eo->Recurrence->WeekOfMonth = new EasProperty('Calendar', $this->toDaysOfMonth($so->Occurrence->OnWeekOfMonth));
					$eo->Recurrence->MonthOfYear = new EasProperty('Calendar', $this->toMonthOfYear($so->Occurrence->OnMonthOfYear));
				}
			}
		}
        
		return $eo;

    }

	
    public function generateSignature(EventObject $eo): string {
        
        // clone self
        $o = clone $eo;
        // remove non needed values
        unset($o->Origin, $o->ID, $o->CID, $o->UUID, $o->Signature, $o->CCID, $o->CEID, $o->CESN, $o->CreatedOn, $o->ModifiedOn);
        // generate signature
        return md5(json_encode($o));

    }
	
	/**
     * Converts EAS (Microsoft/Windows) time zone to DateTimeZone object
     * 
     * @since Release 1.0.0
     * 
     * @param string $zone			eas time zone name
     * 
     * @return DateTimeZone			valid DateTimeZone object on success, or null on failure
     */
	public function fromTimeZone(string $zone): ?DateTimeZone {

		// decode zone from bae64 format
		$zone = base64_decode($zone);
		// convert byte string to array
		$zone = unpack('lbias/a64stdname/vstdyear/vstdmonth/vstdday/vstdweek/vstdhour/vstdminute/vstdsecond/vstdmillis/lstdbias/'
					       . 'a64dstname/vdstyear/vdstmonth/vdstday/vdstweek/vdsthour/vdstminute/vdstsecond/vdstmillis/ldstbias', $zone);
		// extract zone name from array and convert to UTF8
		$name = trim(@iconv('UTF-16', 'UTF-8', $zone['stdname']));
		// convert JMAP time zone name to DateTimeZone object
			return \OCA\JMAPC\Utile\TimeZoneEAS::toDateTimeZone($name);
		
	}

	/**
     * Converts DateTimeZone object to JMAP (Microsoft/Windows) time zone name
     * 
     * @since Release 1.0.0
     * 
     * @param DateTimeZone $zone
     * 
     * @return string valid JMAP time zone name on success, or null on failure
     */ 
	public function toTimeZone(DateTimeZone $zone, DateTime $date = null): string {

		// convert IANA time zone name to EAS time zone name
		$zone = \OCA\JMAPC\Utile\TimeZoneEAS::fromIANA($zone->getName());
		// retrieve time mutation
		$mutation = \OCA\JMAPC\Utile\TimeZoneEAS::findZoneMutation($zone, $date, true);

		if (isset($mutation)) {
			if ($mutation->Type == 'Static') {
				$stdName = $zone;
				$stdBias = $mutation->Alterations[0]->Bias;
				$stdMonth = 0;
				$stdWeek = 0;
				$stdDay = 0;
				$stdHour = 0;
				$stdMinute = 0;
				$dstName = $zone;
				$dstBias = 0;
				$dstMonth = 0;
				$dstWeek = 0;
				$dstDay = 0;
				$dstHour = 0;
				$dstMinute = 0;
			}
			else {
				foreach ($mutation->Alterations as $entry) {
					switch ($entry->Class) {
						case 'Daylight':
							$dstName = $zone;
							$dstBias = $entry->Bias;
							$dstMonth = $entry->Month;
							$dstWeek = $entry->Week;
							$dstDay = $entry->Day;
							$dstHour = $entry->Hour;
							$dstMinute = $entry->Minute;
							break;
						default:
							$stdName = $zone;
							$stdBias = $entry->Bias;
							$stdMonth = $entry->Month;
							$stdWeek = $entry->Week;
							$stdDay = $entry->Day;
							$stdHour = $entry->Hour;
							$stdMinute = $entry->Minute;
							break;
					}
				}
				// convert DST bias to reletive from standard
				$dstBias = ($dstBias - $stdBias) * -1;
			}

			return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl', $stdBias, $stdName, 0, $stdMonth, $stdDay, $stdWeek, $stdHour, $stdMinute, 0, 0, 0, $dstName, 0, $dstMonth, $dstDay, $dstWeek, $dstHour, $dstMinute, 0, 0, $dstBias));
		}
		else {
			return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl', 0, 'UTC', 0, 0, 0, 0, 0, 0, 0, 0, 0, 'UTC', 0, 0, 0, 0, 0, 0, 0, 0, 0));
		}

	}

	function email_split( $str ){
		
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
     * convert remote availability status to event object availability status
	 * 
     * @since Release 1.0.0
     * 
	 * @param string $value		remote availability status value
	 * 
	 * @return string			event object availability status value
	 */
	private function fromAvailability(?string $value): string {
		
		// transposition matrix
		$_tm = array(
			'free' => 'F',
			'busy' => 'B',
		);
		// evaluate if value exists
		if (isset($_tm[$value])) {
			// return transposed value
			return $_tm[$value];
		} else {
			// return default value
			return 'B';
		}
		
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
	private function toAvailability(?string $value): string {
		
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
     * convert remote sensitivity status to event object sensitivity status
	 * 
     * @since Release 1.0.0
     * 
	 * @param string $value		remote sensitivity status value
	 * 
	 * @return string			event object sensitivity status value
	 */
	private function fromSensitivity(?string $value): string {
		
		// transposition matrix
		$_tm = array(
			'public' => 'N',
			'private' => 'P',
			'secret' => 'S',
		);
		// evaluate if value exists
		if (isset($_tm[$value])) {
			// return transposed value
			return $_tm[$value];
		} else {
			// return default value
			return 'N';
		}
		
	}

	/**
     * convert event object sensitivity status to remote sensitivity status
	 * 
     * @since Release 1.0.0
     * 
	 * @param string $value		event object sensitivity status value
	 * 
	 * @return string	 		remote sensitivity status value
	 */
	private function toSensitivity(?string $value): string {
		
		// transposition matrix
		$_tm = array(
			'N' => 'public',
			'P' => 'private',
			'S' => 'secret',
		);
		// evaluate if value exists
		if (isset($_tm[$value])) {
			// return transposed value
			return $_tm[$value];
		} else {
			// return default value
			return 'public';
		}

	}

	/**
     * convert remote days of the week to event object days of the week
	 * 
     * @since Release 1.0.0
     * 
	 * @param int $days - remote days of the week values(s)
	 * @param bool $group - flag to check if days are grouped
	 * 
	 * @return array event object days of the week values(s)
	 */
	private function fromDaysOfWeek(int $days, bool $group = false): array {

		// evaluate if days match any group patterns
		if ($group) {
			if ($days == 65) {
				return [6,7];		// Weekend Days
			}
			elseif ($days == 62) {
				return [1,2,3,4,5];	// Week Days
			}
		}
		// convert day values
		$dow = [];
		if ($days >= 64) {
			$dow[] = 6;		// Saturday
			$days -= 64;
		}
		if ($days >= 32) {
			$dow[] = 5;		// Friday
			$days -= 32;
		}
		if ($days >= 16) {
			$dow[] = 4;		// Thursday
			$days -= 16;
		}
		if ($days >= 8) {
			$dow[] = 3;		// Wednesday
			$days -= 8;
		}
		if ($days >= 4) {
			$dow[] = 2;		// Tuesday
			$days -= 4;
		}
		if ($days >= 2) {
			$dow[] = 1;		// Monday
			$days -= 2;
		}
		if ($days >= 1) {
			$dow[] = 7;		// Sunday
			$days -= 1;
		}
		// sort days
		asort($dow);
		// return converted days
		return $dow;

	}

	/**
     * convert event object days of the week to remote days of the week
	 * 
     * @since Release 1.0.0
     * 
	 * @param array $days - event object days of the week values(s)
	 * @param bool $group - flag to check if days can be grouped 
	 * 
	 * @return string remote days of the week values(s)
	 */
	private function toDaysOfWeek(array $days, bool $group = false): int {
		
		// evaluate if days match any group patterns
		if ($group) {
			sort($days);
			if ($days == [1,2,3,4,5]) {
				return 62;		// Week	Days
			}
			elseif ($days == [6,7]) {
				return 65;		// Weekend Days
			}
		}
        // convert day values
		$dow = 0;
        foreach ($days as $key => $entry) {
			switch ($entry) {
				case 1:
					$dow += 2;	// Monday
					break;
				case 2:
					$dow += 4;	// Tuesday
					break;
				case 3:
					$dow += 8;	// Wednesday
					break;
				case 4:
					$dow += 16;	// Thursday
					break;
				case 5:
					$dow += 32;	// Friday
					break;
				case 6:
					$dow += 64;	// Saturday
					break;
				case 7:
					$dow += 1;	// Sunday
					break;
			}
        }
        // return converted days
        return $dow;

	}

	/**
     * convert remote days of the month to event object days of the month
	 * 
     * @since Release 1.0.0
     * 
	 * @param sting $days - remote days of the month values(s)
	 * 
	 * @return array event object days of the month values(s)
	 */
	private function fromDaysOfMonth(string $days): array {

		// return converted days
		return [$days];

	}

	/**
     * convert event object days of the month to remote days of the month
	 * 
     * @since Release 1.0.0
     * 
	 * @param array $days - event object days of the month values(s)
	 * 
	 * @return string remote days of the month values(s)
	 */
	private function toDaysOfMonth(array $days): string {

        // return converted days
        return $days[0];

	}

	/**
     * convert remote week of the month to event object week of the month
	 * 
     * @since Release 1.0.0
     * 
	 * @param sting $weeks - remote week of the month values(s)
	 * 
	 * @return array event object week of the month values(s)
	 */
	private function fromWeekOfMonth(string $weeks): array {

		// weeks conversion reference
		$_tm = array(
			'1' => 1,
			'2' => 2,
			'3' => 3,
			'4' => 4,
			'5' => -1
		);
		// convert week values
		foreach ($weeks as $key => $entry) {
			if (isset($_tm[$entry])) {
				$weeks[$key] = $_tm[$entry];
			}
		}
		// return converted weeks
		return $weeks;

	}

	/**
     * convert event object week of the month to remote week of the month
	 * 
     * @since Release 1.0.0
     * 
	 * @param array $weeks - event object week of the month values(s)
	 * 
	 * @return string remote week of the month values(s)
	 */
	private function toWeekOfMonth(array $weeks): string {

		// weeks conversion reference
		$_tm = array(
			1 => '1',
			2 => '2',
			3 => '3',
			4 => '4',
			-1 => '5',
			-2 => '4'
		);
		// convert week values
        foreach ($weeks as $key => $entry) {
            if (isset($_tm[$entry])) {
                $weeks[$key] = $_tm[$entry];
            }
        }
        // convert weeks to string
        $weeks = implode(',', $weeks);
        // return converted weeks
        return $weeks;

	}

	/**
     * convert remote month of the year to event object month of the year
	 * 
     * @since Release 1.0.0
     * 
	 * @param sting $months - remote month of the year values(s)
	 * 
	 * @return array event object month of the year values(s)
	 */
	private function fromMonthOfYear(string $months): array {

		// return converted months
		return [$months];

	}

	/**
     * convert event object month of the year to remote month of the year
	 * 
     * @since Release 1.0.0
     * 
	 * @param array $weeks - event object month of the year values(s)
	 * 
	 * @return string remote month of the year values(s)
	 */
	private function toMonthOfYear(array $months): string {

        // return converted months
        return $months[0];

	}

	/**
     * convert remote attendee type to event object type
	 * 
     * @since Release 1.0.0
     * 
	 * @param int $value		remote attendee type value
	 * 
	 * @return string			event object attendee type value
	 */
	private function fromAttendeeType(?int $value): string {
		
		// type conversion reference
		$_type = array(
			1 => 'R', 	// Required
			2 => 'O',	// Optional
			3 => 'A'	// Asset / Resource
		);
		// evaluate if type value exists
		if (isset($_type[$value])) {
			// return converted type value
			return $_type[$value];
		} else {
			// return default type value
			return 'R';
		}
		
	}

	/**
     * convert event object attendee type to remote attendee type
	 * 
     * @since Release 1.0.0
     * 
	 * @param string $value		event object attendee type value
	 * 
	 * @return int	 			remote attendee type value
	 */
	private function toAttendeeType(?string $value): int {
		
		// type conversion reference
		$_type = array(
			'R' => 1, 	// Required
			'O' => 2,	// Optional
			'A' => 3	// Asset / Resource
		);
		// evaluate if type value exists
		if (isset($_type[$value])) {
			// return converted type value
			return $_type[$value];
		} else {
			// return default type value
			return 1;
		}

	}

	/**
     * convert remote attendee status to event object status
	 * 
     * @since Release 1.0.0
     * 
	 * @param int $value		remote attendee status value
	 * 
	 * @return string			event object attendee status value
	 */
	private function fromAttendeeStatus(?int $value): string {
		
		// status conversion reference
		$_status = array(
			0 => 'U', 	// Unknown
			2 => 'T',	// Tentative
			3 => 'A',	// Accepted
			4 => 'D',	// Declined
			5 => 'N'	// Not responded
		);
		// evaluate if status value exists
		if (isset($_status[$value])) {
			// return converted status value
			return $_status[$value];
		} else {
			// return default status value
			return 'N';
		}
		
	}

	/**
     * convert event object attendee status to remote attendee status
	 * 
     * @since Release 1.0.0
     * 
	 * @param string $value		event object attendee status value
	 * 
	 * @return int	 			remote attendee status value
	 */
	private function toAttendeeStatus(?string $value): int {
		
		// status conversion reference
		$_status = array(
			'U' => 0,
			'T' => 2,
			'A' => 3,
			'D' => 4,
			'N' => 5
		);
		// evaluate if status value exists
		if (isset($_status[$value])) {
			// return converted status value
			return $_status[$value];
		} else {
			// return default status value
			return 5;
		}

	}

}
