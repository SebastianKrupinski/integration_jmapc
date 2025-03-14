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
use JmapClient\Requests\Contacts\ContactQuery;
use JmapClient\Responses\Calendar\CalendarParameters as CalendarParametersResponse;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\BaseStringCollection;
use OCA\JMAPC\Objects\Common\Filters\IFilter;
use OCA\JMAPC\Objects\Common\Range\IRange;
use OCA\JMAPC\Objects\Common\Sort\ISort;
use OCA\JMAPC\Objects\DeltaObject;
use OCA\JMAPC\Objects\Event\EventCollectionObject;
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

	public function __construct () {}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
        // evaluate if client is connected 
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
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
	public function collectionFetch(string $id): ?EventCollectionObject {
		// construct request
		$r0 = new CalendarGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		if (!empty($id)) {
            $r0->target($id);
        }
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to collection object
		if ($response->object(0) instanceof CalendarParametersResponse) {
			$co = $response->object(0);
			$collection = new EventCollectionObject();
			$collection->Id = $co->id();
			$collection->Label = $co->label();
			$collection->Description = $co->description();
			$collection->Priority = $co->priority();
			$collection->Visibility = $co->visible();
			$collection->Color = $co->color();
			return $collection;
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
	public function collectionCreate(EventCollectionObject $collection): string {
		// construct request
		$r0 = new CalendarSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		$m0 = $r0->create('1');
		if ($collection->Label) {
			$m0->label($collection->Label);
		}
		if ($collection->Description) {
			$m0->description($collection->Description);
		}
		if ($collection->Priority) {
			$m0->priority($collection->Priority);
		}
		if ($collection->Visibility) {
			$m0->visible($collection->Visibility);
		}
		if ($collection->Color) {
			$m0->color($collection->Color);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return (string) $response->created()['1']['id'];
    }

    /**
     * update collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionUpdate(string $id, EventCollectionObject $collection): string {
        // construct request
		$r0 = new CalendarSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		$m0 = $r0->update($id);
		$m0->label($collection->Label);
		$m0->description($collection->Description);
		$m0->priority($collection->Priority);
		$m0->visible($collection->Visibility);
		$m0->color($collection->Color);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

    /**
     * delete collection in remote storage
     * 
     * @since Release 1.0.0
	 * 
	 */
    public function collectionDelete(string $id): string {
        // construct request
		$r0 = new CalendarSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->delete($id);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return (string) $response->deleted()[0];
    }

	/**
     * list of collections in remote storage
     * 
     * @since Release 1.0.0
     * 
	 * @param string|null $location			Id of parent collection
	 * @param string|null $granularity		Amount of detail to return		
	 * @param int|null $depth				Depth of sub collections to return
	 * 
	 * @return array<string,EventCollectionObject>
	 */
	public function collectionList(?string $location = null, ?string $granularity = null, ?int $depth = null): array {
		// construct request
 		$r0 = new CalendarGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set target to query request
        if ($location !== null) {
            $r0->target($location);
        }
		// transceive
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
		// convert jmap objects to collection objects
        $list = [];
		foreach ($response->objects() as $co) {
            $collection = new EventCollectionObject();
			$collection->Id = $co->id();
			$collection->Label = $co->label();
			$collection->Description = $co->description();
			$collection->Priority = $co->priority();
			$collection->Visibility = $co->visible();
			$collection->Color = $co->color();
			$list[] = $collection;
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
	public function entityFetch(string $location, string $id, string $granularity = 'D'): EventObject|null {
		// construct request
		$r0 = new EventGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target($id);
		// select properties to return
		if ($granularity === 'B') {
			$r0->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to event object
		$eo = $this->toEventObject($response->object(0));
		$eo->Signature = $this->generateSignature($eo);

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
		$r0->create('1', $entity)->in($location);
		// transceive
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
			$ro->Signature = $this->generateSignature($ro);
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
	public function entityModify(string $location, string $id, EventObject $so): EventObject|null {
		// convert entity
		$entity = $this->fromEventObject($so);
		// construct set request
		$r0 = new EventSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($id, $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to event object
		if (array_key_exists($id, $response->updated())) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $id;
			$ro->ModifiedOn = isset($response->updated()[$id]['updated']) ? new DateTimeImmutable($response->updated()[$id]['updated']) : null;
			$ro->Signature = $this->generateSignature($ro);
		} else {
			$ro = null;
		}
		// return entity information
		return $ro;
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
		// transceive
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
		// transceive
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
	 * @param string|null $location			Id of parent collection
	 * @param string|null $granularity		Amount of detail to return		
	 * @param IRange|null $range			Range of collections to return
	 * @param IFilter|null $filter			Properties to filter by
	 * @param ISort|null $sort				Properties to sort by
	 */
	public function entityList(?string $location = null, ?string $granularity = null, ?IRange $range = null, ?IFilter $filter = null, ?ISort $sort = null, ?int $depth = null): array {
		// construct query request
		$r0 = new EventQuery($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set location constraint
        if (!empty($location)) {
    		$r0->filter()->in($location);
        }
		// range constraint(s)
		if ($range !== null) {
			match($range->type()->value) {
				'absolute' => $r0->startAbsolute($range->getStart())->limitAbsolute($range->getCount()),
				'relative' => $r0->startRelative($range->getStart())->limitRelative($range->getCount()),
				default => null
			};
		}
		// filter constraint(s)
		if ($filter !== null) {
			foreach ($filter->conditions() as $condition) {
				[$operator, $property, $value] = $condition;
				match($property) {
					'before' => $r0->filter()->before($value),
					'after' => $r0->filter()->after($value),
					'uid' => $r0->filter()->uid($value),
					'text' => $r0->filter()->text($value),
					'title' => $r0->filter()->title($value),
					'description' => $r0->filter()->description($value),
					'location' => $r0->filter()->location($value),
					'owner' => $r0->filter()->owner($value),
					'attendee' => $r0->filter()->attendee($value),
					default => null
				};
			}
		}
		// sort constraint(s)
		if ($sort !== null) {
			foreach ($sort->conditions() as $condition) {
				[$property, $direction] = $condition;
				match($property) {
					'created' => $r0->sort()->created($direction),
					'modified' => $r0->sort()->updated($direction),
					'start' => $r0->sort()->start($direction),
					'uid' => $r0->sort()->uid($direction),
					'recurrence' => $r0->sort()->recurrence($direction),
					default => null
				};
			}
		}
		// construct get request
		$r1 = new EventGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// select properties to return
        if ($granularity === 'B') {
            $r1->property(...$this->entityPropertiesBasic);
        }
		// transceive
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to message objects
		$state = $response->state();
		$list = $response->objects();
		foreach ($list as $id => $entry) {
			$list[$id] = $this->toEventObject($entry);
		}
		// return message collection
		return ['list' => $list, 'state' => $state];
		
    }

	/**
     * delta for entities in remote storage
     * 
     * @since Release 1.0.0
     * 
     * @return DeltaObject
	 */
    public function entityDelta(?string $location, string $state, string $granularity = 'D'): DeltaObject {

        if (empty($state)) {
            $results = $this->entityList($location, 'B');
            $delta = new DeltaObject();
            $delta->signature = $results['state'];
            foreach ($results['list'] as $entry) {
                $delta->additions[] = $entry->ID;
            }
            return $delta;
        }
        if (empty($location)) {
            return $this->entityDeltaDefault($state, $granularity);
        } else {
            return $this->entityDeltaSpecific($location, $state, $granularity);
        }
    }

    /**
     * delta of changes for specific collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityDeltaSpecific(?string $location, string $state, string $granularity = 'D'): DeltaObject {
        // construct set request
		$r0 = new EventQueryChanges($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
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
		// transceive
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
		// convert jmap object to delta object
        $delta = new DeltaObject();
        $delta->signature = $response->stateNew();
        $delta->additions = new BaseStringCollection($response->created());
        $delta->modifications = new BaseStringCollection($response->updated());
        $delta->deletions = new BaseStringCollection($response->deleted());

        return $delta;
    }

    /**
     * delta of changes in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityDeltaDefault(string $state, string $granularity = 'D'): DeltaObject {
        // construct set request
		$r0 = new EventChanges($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set state constraint
		if (!empty($state)) {
			$r0->state($state);
		} else {
			$r0->state('');
		}
		// transceive
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
		// convert jmap object to delta object
        $delta = new DeltaObject();
        $delta->signature = $response->stateNew();
        $delta->additions = new BaseStringCollection($response->created());
        $delta->modifications = new BaseStringCollection($response->updated());
        $delta->deletions = new BaseStringCollection($response->deleted());
		
		return $delta;
    }

	/**
     * convert jmap object to event object
     * 
     * @since Release 1.0.0
     * 
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
		if ($so->in()) {
			$eo->CID = $so->in()[0];
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
			$entity = new EventLocationPhysicalObject();
			$entity->Id  = (string)$id;
			$entity->Name = $entry->label();
			$entity->Description = $entry->description();
			$eo->LocationsPhysical[$id] = $entity;
		}
		// virtual location(s)
		foreach ($so->virtualLocations() as $id => $entry) {
			$entity = new EventLocationVirtualObject();
			$entity->Id  = (string)$id;
			$entity->Name = $entry->label();
			$entity->Description = $entry->description();
			$eo->LocationsVirtual[$id] = $entity;
		}
		// availability
		if ($so->availability()) {
			$eo->Availability = match (strtolower((string)$so->availability())) {
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
			$eo->Sensitivity = match (strtolower((string)$so->privacy())) {
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
			$entity = new EventParticipantObject();
			$entity->Id = (string)$id;
			$entity->Address = $entry->address();
			$entity->Name = $entry->name();
			$entity->Description = $entry->description();
			$entity->Comment = $entry->comment();
			$entity->Type = match (strtolower((string)$entry->kind())) {
				'individual' => EventParticipantTypes::Individual,
				'group' => EventParticipantTypes::Group,
				'resource' => EventParticipantTypes::Resource,
				'location' => EventParticipantTypes::Location,
				default => EventParticipantTypes::Unknown,
			};
			$entity->Status = match (strtolower((string)$entry->status())) {
				'accepted' => EventParticipantStatusTypes::Accepted,
				'declined' => EventParticipantStatusTypes::Declined,
				'tentative' => EventParticipantStatusTypes::Tentative,
				'delegated' => EventParticipantStatusTypes::Delegated,
				default => EventParticipantStatusTypes::None,
			};
			
			foreach ($entry->roles() as $role => $value) {
				$entity->Roles[$role] = EventParticipantRoleTypes::from($role);
			}
			$eo->Participants[$id] = $entity;
		}
		// notification(s)
		foreach ($so->notifications() as $id => $entry) {
			$trigger = $entry->trigger();
			$entity = new EventNotificationObject();
			$entity->Type = match (strtolower((string)$entry->type())) {
				'email' => EventNotificationTypes::Email,
				default => EventNotificationTypes::Visual,
			};
			$entity->Pattern = match (strtolower((string)$trigger->type())) {
				'absolute' => EventNotificationPatterns::Absolute,
				'relative' => EventNotificationPatterns::Relative,
				default => EventNotificationPatterns::Unknown,
			};
			if ($entity->Pattern === EventNotificationPatterns::Absolute) {
				$entity->When = $trigger->when();
			} elseif ($entity->Pattern === EventNotificationPatterns::Relative) {
				$entity->Anchor = match (strtolower((string)$trigger->anchor())) {
					'end' => EventNotificationAnchorTypes::End,
					default => EventNotificationAnchorTypes::Start,
				};
				$entity->Offset = $trigger->offset();
			}
			$eo->Notifications[$id] = $entity;
		}
		// occurrence(s)
		foreach ($so->recurrenceRules() as $id => $entry) {
			$entity = new EventOccurrenceObject();
			
			// Interval
			if ($entry->interval() !== null) {
				$entity->Interval = $entry->interval();
			}
			// Iterations
			if ($entry->count() !== null) {
				$entity->Iterations = $entry->count();
			}
			// Conclusion
			if ($entry->until() !== null) {
				$entity->Concludes = new DateTime($entry->until());
			}
			// Daily
			if ($entry->frequency() === 'daily') {
				$entity->Pattern = EventOccurrencePatternTypes::Absolute;
				$entity->Precision = EventOccurrencePrecisionTypes::Daily;
            }
			// Weekly
			if ($entry->frequency() === 'weekly') {
				$entity->Pattern = EventOccurrencePatternTypes::Absolute;
				$entity->Precision = EventOccurrencePrecisionTypes::Weekly;
				$entity->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
            }
			// Monthly 
			if ($entry->frequency() === 'monthly') {
				$entity->Precision = EventOccurrencePrecisionTypes::Monthly;
				// Absolute
				if (count($entry->byDayOfMonth())) {
					$entity->Pattern = EventOccurrencePatternTypes::Absolute;
					$entity->OnDayOfMonth = $entry->byDayOfMonth();
				}
				// Relative
				else {
					$entity->Pattern = EventOccurrencePatternTypes::Relative;
					$entity->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
					$entity->OnPosition = $entry->byPosition();
				}
            }
			// Yearly
			if ($entry->frequency() === 'yearly') {
				$entity->Precision = EventOccurrencePrecisionTypes::Yearly;
				// nth day of year
				if (count($entry->byDayOfYear())) {
					$entity->Pattern = EventOccurrencePatternTypes::Absolute;
					$entity->OnDayOfYear = $entry->byDayOfYear();
					$entity->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
				}
				// nth week of year
				elseif (count($entry->byWeekOfYear())) {
					$entity->Pattern = EventOccurrencePatternTypes::Relative;
					$entity->OnWeekOfYear = $entry->byWeekOfYear();
					$entity->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
				}
				// nth month of year
				elseif (count($entry->byMonthOfYear())) {
					if (count($entry->byDayOfMonth())) {
						$entity->Pattern = EventOccurrencePatternTypes::Absolute;
						$entity->OnDayOfMonth = $entry->byDayOfMonth();
					} else {
						$entity->Pattern = EventOccurrencePatternTypes::Relative;
						$entity->OnDayOfWeek = $this->fromDaysOfWeek($entry->byDayOfWeek());
						$entity->OnPosition = $entry->byPosition();
					}
				}
			}
			// add to collection
			$eo->OccurrencePatterns[] = $entity;
		}
		
		return $eo;

    }

	/**
     * convert event object to jmap object
     * 
     * @since Release 1.0.0
     * 
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
			$entity = $to->physicalLocations($entry->Id);
			if ($entry->Name) {
				$entity->label($entry->Name);
			}
			if ($entry->Description) {
				$entity->description($entry->Description);
			}
		}
		// virtual location(s)
        foreach ($eo->LocationsVirtual as $entry) {
			$entity = $to->virtualLocations($entry->Id);
			if ($entry->Name) {
				$entity->label($entry->Name);
			}
			if ($entry->Description) {
				$entity->description($entry->Description);
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
			$entity = $to->participants($entry->Id);
			if ($entry->Address) {
				$entity->address($entry->Address);
				$entity->send('imip', 'mailto:' . $entry->Address);
			}
			if ($entry->Name) {
				$entity->name($entry->Name);
			}
			if ($entry->Description) {
				$entity->description($entry->Description);
			}
			if ($entry->Comment) {
				$entity->comment($entry->Comment);
			}
			if ($entry->Type) {
				$entity->kind(match ($entry->Type) {
					EventParticipantTypes::Individual => 'group',
					EventParticipantTypes::Individual => 'resource',
					EventParticipantTypes::Individual => 'location',
					default => 'individual',
				});
			}
			if ($entry->Status) {
				$entity->kind(match ($entry->Status) {
					EventParticipantStatusTypes::Accepted => 'accepted',
					EventParticipantStatusTypes::Declined => 'declined',
					EventParticipantStatusTypes::Tentative => 'tentative',
					EventParticipantStatusTypes::Delegated => 'delegated',
					default => 'needs-action',
				});
			}
			if (!empty($entry->Roles)) {
				$roles = [];
				foreach ($entry->Roles as $role) {
					$roles[] = $role->value;
				}
				$entity->roles(...$roles);
			}
		}
		// notification(s)
        foreach ($eo->Notifications as $entry) {
			$entity = $to->notifications($entry->Id);
			if ($entry->Type) {
				$entity->type(match ($entry->type) {
					EventNotificationTypes::Email => 'email',
					default => 'display',
				});
			}
			if ($entry->Pattern === EventNotificationPatterns::Absolute) {
				$entity->trigger('absolute')->when($entry->When);
			} elseif ($entry->Pattern === EventNotificationPatterns::Relative) {
				if ($entry->Anchor === EventNotificationAnchorTypes::End) {
					$entity->trigger('offset')->anchor('end')->offset($entry->Offset);
				} else {
					$entity->trigger('offset')->anchor('start')->offset($entry->Offset);
				}
			} else {
				$entity->trigger('unknown');
			}
		}
		// occurrence(s)
        foreach ($eo->OccurrencePatterns as $index => $entry) {
			$entity = $to->recurrenceRules($index);
			if ($entry->Precision) {
				$entity->frequency(match ($entry->Precision) {
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
				$entity->interval($entry->Interval);
			}
			if ($entry->Iterations) {
				$entity->count($entry->Iterations);
			}
			if ($entry->Concludes) {
				$entity->until($entry->Concludes);
			}
			if ($entry->OnDayOfWeek !== []){
				foreach ($entry->OnDayOfWeek as $id => $day) {
					$nDay = $entity->byDayOfWeek($id);
					$nDay->day($day);
				}	
			}
			if (!empty($entry->OnDayOfMonth)){
				$entity->byDayOfMonth(...$entry->OnDayOfMonth);
			}
			if (!empty($entry->OnDayOfYear)){
				$entity->byDayOfYear(...$entry->OnDayOfYear);
			}
			if (!empty($entry->OnWeekOfMonth)){
				$entity->byWeekOfYear(...$entry->OnWeekOfMonth);
			}
			if (!empty($entry->OnWeekOfYear)){
				$entity->byWeekOfYear(...$entry->OnWeekOfYear);
			}
			if (!empty($entry->OnMonthOfYear)){
				$entity->byMonthOfYear(...$entry->OnMonthOfYear);
			}
			if (!empty($entry->OnHour)){
				$entity->byHour(...$entry->OnHour);
			}
			if (!empty($entry->OnMinute)){
				$entity->byMinute(...$entry->OnMinute);
			}
			if (!empty($entry->OnSecond)){
				$entity->bySecond(...$entry->OnSecond);
			}
			if (!empty($entry->OnPosition)){
				$entity->byPosition(...$entry->OnPosition);
			}
		}
        
		return $to;

    }

	
    public function generateSignature(EventObject $eo): string {
        
        // clone self
        $o = clone $eo;
        // remove non needed values
        unset(
			$o->Origin,
			$o->ID,
			$o->CID,
			$o->Signature,
			$o->CCID,
			$o->CEID,
			$o->CESN,
			$o->UUID,
			$o->CreatedOn,
			$o->ModifiedOn
		);

        // generate signature
        return md5(json_encode($o, JSON_PARTIAL_OUTPUT_ON_ERROR));

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
