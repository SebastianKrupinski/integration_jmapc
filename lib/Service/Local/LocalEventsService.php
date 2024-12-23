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

namespace OCA\JMAPC\Service\Local;

use Datetime;
use DateTimeZone;
use DateInterval;
use OC\Files\Node\LazyUserFolder;
use OCA\JMAPC\Objects\Event\EventAvailabilityTypes;
use OCA\JMAPC\Objects\Event\EventLocationPhysicalObject;
use OCA\JMAPC\Objects\Event\EventNotificationAnchorTypes;
use OCA\JMAPC\Objects\Event\EventNotificationObject;
use OCA\JMAPC\Objects\Event\EventNotificationPatterns;
use OCA\JMAPC\Objects\Event\EventNotificationTypes;
use OCA\JMAPC\Store\EventStore;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\Event\EventOccurrenceObject;
use OCA\JMAPC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\JMAPC\Objects\Event\EventParticipantObject;
use OCA\JMAPC\Objects\Event\EventParticipantRoleTypes;
use OCA\JMAPC\Objects\Event\EventParticipantStatusTypes;
use OCA\JMAPC\Objects\Event\EventParticipantTypes;
use OCA\JMAPC\Objects\Event\EventSensitivityTypes;
use OCA\JMAPC\Objects\Event\EventTagCollection;
use OCA\JMAPC\Objects\EventCollection;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Store\EventEntity;
use OCA\JMAPC\Utile\UUID;
use Sabre\VObject\Reader;
use Sabre\VObject\Component\VEvent;

class LocalEventsService {
    
    protected string $DateFormatUTC = 'Ymd\THis\Z';
    protected string $DateFormatDateTime = 'Ymd\THis';
    protected string $DateFormatDateOnly = 'Ymd';
	protected EventStore $_Store;
	protected ?DateTimeZone $SystemTimeZone = null;
	protected ?DateTimeZone $UserTimeZone = null;
	protected string $UserAttachmentPath = '';
	protected ?LazyUserFolder $FileStore = null;

	public function __construct () {
	}
    
    public function initialize(EventStore $Store) {

		$this->_Store = $Store;

	}

	/**
     * retrieve collection from local storage
     * 
	 * @param string $cid            Collection ID
	 * 
	 * @return EventCollection  EventCollection on success / null on fail
	 */
	public function collectionFetch(int $cid): ?EventCollection {

        // retrieve object properties
        $ce = $this->_Store->collectionFetch($cid);
        // evaluate if object properties where retrieve
        if (is_array($ce) && count($ce) > 0) {
            // construct object and return
            return new EventCollection(
                $ce->getId(),
                $ce->getLabel(),
                null,
                $ce->getVisible(),
                $ce->getColor()
            );
        }
        else {
            // return nothing
            return null;
        }

    }

    /**
     * delete collection from local storage
     * 
     * @since Release 1.0.0
     * 
     * @param int $cid              collection id
     * 
     * @return void
	 */
    public function collectionDeleteById(int $cid): void {
        
        // delete entities from data store
        $this->_Store->entityDeleteByCollection($cid);
        $this->_Store->collectionDeleteById($cid);

    }

    /**
     * retrieve list of entities from local storage
     * 
     * @param int $cid              collection id
     * 
     * @return array                collection of entities
	 */
	public function entityList(int $cid, string $particulars): array {

        return $this->_Store->entityListByCollection($cid);

    }

    /**
     * retrieve the differences for specific collection from a specific point from local storage
     * 
     * @param string $uid           user id
	 * @param int $cid              collection id
     * @param string $signature     collection signature
	 * 
	 * @return array                collection of differences
	 */
	public function entityDelta(int $cid, string $signature): array {

        // retrieve collection differences
        $lcc = $this->_Store->chronicleReminisce($cid, $signature);
        // return collection differences
		return $lcc;
        
    }

	/**
     * retrieve entity object from local storage
     * 
     * @param int $id               entity id
	 * 
	 * @return EventObject|null
	 */
	public function entityFetch(int $id): EventObject|null {

        // retrieve entity object
        $eo = $this->_Store->entityFetch($id);
        // evaluate if entity was retrieved
        if ($eo instanceof EventEntity) {
            return $this->fromEventEntity($eo);
        } else {
            return null;
        }

    }

    /**
     * retrieve entity by correlation id from local storage
     * 
     * @param int $cid              collection id
	 * @param string $ccid          correlation collection id
     * @param string $ceid          correlation entity id
	 * 
	 * @return EventObject|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): EventObject|null {

        // retrieve entity object
        $eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
		// evaluate if entity was retrieved
        if ($eo instanceof EventEntity) {
            return $this->fromEventEntity($eo);
        } else {
            return null;
        }

    }

    /**
     * create entity in local storage
     * 
	 * @param string $uid           user id
     * @param int $sid              service id
	 * @param int $cid              collection id
     * @param EventObject $so       source object
	 * 
	 * @return object               Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityCreate(string $uid, int $sid, int $cid, EventObject $so): ?object {

        // convert event object to data store entity
        $eo = $this->toEventEntity(
            $so,
            [
                'Uid' => $uid,
                'Sid' => $sid,
                'Cid' => $cid,
            ]
        );
        // create entry in data store
        $eo = $this->_Store->entityCreate($eo);
        // return result
        if ($eo) {
            return (object) array('ID' => $eo->getId(), 'Signature' => $eo->getSignature());
        } else {
            return null;
        }

    }
    
    /**
     * modify entity in local storage
     * 
	 * @param string $uid           user id
     * @param int $sid              service id
	 * @param int $cid              collection id
	 * @param int $eid              entity id
     * @param EventObject $so       source object
	 * 
	 * @return object               Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityModify(string $uid, int $sid, int $cid, int $eid, EventObject $so): ?object {

        // convert event object to data store entity
        $eo = $this->toEventEntity(
            $so,
            [
                'Id' => $eid,
                'Uid' => $uid,
                'Sid' => $sid,
                'Cid' => $cid,
            ]
        );
        // modify entry in data store
        $eo = $this->_Store->entityModify($eo);
        // return result
        if ($eo) {
            return (object) array('ID' => $eo->getId(), 'Signature' => $eo->getSignature());
        } else {
            return null;
        }

    }
    
    /**
     * delete entity from local storage
     * 
	 * @param int $eid              entity id
	 * 
	 * @return bool
	 */
	public function entityDeleteById(int $eid): bool {

        // delete entry from data store
        $rs = $this->_Store->entityDeleteById($eid);
        // return result
        if ($rs) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * delete entity from local storage by remote id
     * 
     * @param int $cid              collection id
	 * @param string $ccid          correlation collection id
     * @param string $ceid          correlation entity id
	 * 
	 * @return bool
	 */
	public function entityDeleteByCorrelation(int $cid, string $ccid, string $ceid): bool {
        // retrieve entity
        $eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
        // evaluate if entity was retrieved
        if ($eo instanceof EventEntity) {
            // delete entry from data store
            $eo = $this->_Store->entityDelete($eo);
            return true;
        } else {
            return false;
        }

    }

    /**
     * convert store entity to event object
     * 
     * @since Release 1.0.0
     * 
	 * @param EventEntity $so
     * @param array<string,mixed>
	 * 
	 * @return EventObject
	 */
	public function fromEventEntity(EventEntity $so, array $additional = []): EventObject {

        // prase vData
        $vObject = Reader::read($so->getData());
        // convert entity
        $to = $this->fromVEvent($vObject->VEVENT);
        $to->ID = $so->getId();
        $to->CID = $so->getCid();
        $to->Signature = $so->getSignature();
        $to->CCID = $so->getCcid();
        $to->CEID = $so->getCeid();
        $to->CESN = $so->getCesn();
        $to->UUID = $so->getUuid();
        // override / assign additional values
        foreach ($additional as $label => $value) {
            if (isset($to->$label)) {
                $to->$label = $value;
            }
        }

        return $to;
    }

    /**
     * convert event object to store entity
     * 
     * @since Release 1.0.0
     * 
	 * @param EventObject $so
     * @param array<string,mixed>
	 * 
	 * @return EventEntity
	 */
	public function toEventEntity(EventObject $so, array $additional = []): EventEntity {

        // construct entity
        $to = new EventEntity();
        // convert source object to entity
        $to->setData("BEGIN:VCALENDAR\nVERSION:2.0\n" . $this->fromEventObject($so)->serialize() . "\nEND:VCALENDAR");
        $to->setUuid($so->UUID);
        $to->setSignature(md5($to->getData()));
        $to->setCcid($so->CCID);
        $to->setCeid($so->CEID);
        $to->setCesn($so->CESN);
        $to->setLabel($so->Label);
        $to->setDescription($so->Description);
        $to->setStartson($so->StartsOn->setTimezone(new DateTimeZone('UTC'))->format('U'));
        $to->setEndson($so->EndsOn->setTimezone(new DateTimeZone('UTC'))->format('U'));
        // override / assign additional values
        foreach ($additional as $key => $value) {
			$method = 'set' . ucfirst($key);
			$to->$method($value);
		}

        return $to;
    }

    /**
     * convert vevent object to event object
     * 
     * @since Release 1.0.0
     * 
	 * @param VEvent $so
	 * 
	 * @return EventObject
	 */
	public function fromVEvent(VEvent $so): EventObject {
		
        // construct target object
		$to = new EventObject();
        // Origin
		$to->Origin = OriginTypes::Internal;
        // universal id
        if (isset($so->UID)) {
            $to->UUID = trim($so->UID->getValue());
        }
        // creation date time
        if (isset($so->CREATED)) {
            $to->CreatedOn = new DateTime($so->CREATED->getValue());
        }
        // modification date time
        if (isset($so->{'LAST-MODIFIED'})) {
            $to->ModifiedOn = new DateTime($so->{'LAST-MODIFIED'}->getValue());
        }
        // sequence
        if (isset($so->SEQUENCE)) {
            $to->Sequence = (int)$so->SEQUENCE->getValue();
        }
        // time zone
        if (isset($so->{'X-TIMEZONE'})) {
            $to->TimeZone = new DateTimeZone($so->{'X-TIMEZONE'}->getValue());
        }
        // Starts Date/Time
        // Starts Time Zone
        if (isset($so->DTSTART)) {
            if (isset($so->DTSTART->parameters['TZID'])) {
                $to->StartsTZ = new DateTimeZone($so->DTSTART->parameters['TZID']->getValue());
            }
            elseif (str_contains($so->DTSTART, 'Z')) {
                $to->StartsTZ = new DateTimeZone('UTC');
            }
            elseif ($this->UserTimeZone instanceof \DateTimeZone) {
                $to->StartsTZ = $this->UserTimeZone;
            }
            else {
                $to->StartsTZ = $this->SystemTimeZone;
            }
            $to->StartsOn = new DateTime($so->DTSTART->getValue(), $to->StartsTZ);
        }
        // Ends Date/Time
        // Ends Time Zone
        if (isset($so->DTEND)) {
            if (isset($so->DTEND->parameters['TZID'])) {
                $to->EndsTZ = new DateTimeZone($so->DTEND->parameters['TZID']->getValue());
            }
            elseif (str_contains($so->DTSTART, 'Z')) {
                $to->EndsTZ = new DateTimeZone('UTC');
            }
            elseif ($this->UserTimeZone instanceof \DateTimeZone) {
                $to->EndsTZ = $this->UserTimeZone;
            }
            else {
                $to->EndsTZ = $this->SystemTimeZone;
            }
            $to->EndsOn = new DateTime($so->DTEND->getValue(), $to->EndsTZ);
        }
        // duration
        if (isset($so->DURATION)) {
            $to->Duration = new DateInterval($so->DURATION->getValue());
        }
        // Label
        if (isset($so->SUMMARY)) {
            $to->Label = trim($so->SUMMARY->getValue());
        }
        // Description
        if (isset($so->DESCRIPTION)) {
            if (!empty(trim($so->DESCRIPTION->getValue()))) {
                $to->Description = trim($so->DESCRIPTION->getValue());
            }
        }
        // Location
        if (isset($so->LOCATION)) {
            foreach ($so->LOCATION as $id => $entry) {
                $location = new EventLocationPhysicalObject();
                $location->Id  = $id;
                $location->Name = trim($entry->getValue());
                //$location->Description = $entry->description();
                $to->LocationsPhysical[$id] = $location;
            }
        }
        // Availability
        if (isset($so->TRANSP)) {
            $to->Availability = match (strtoupper($so->TRANSP->getValue())) {
                'FREE' => EventAvailabilityTypes::Free,
                default => EventAvailabilityTypes::Busy,
            };
        }
        // Priority
        if (isset($so->PRIORITY)) {
            $to->Priority = (int)trim($so->PRIORITY->getValue());
        }
        // Sensitivity
        if (isset($so->CLASS)) {
            $to->Sensitivity = match (strtoupper($so->CLASS->getValue())) {
                'PRIVATE' => EventSensitivityTypes::Private,
                'CONFIDENTIAL' => EventSensitivityTypes::Secret,
                default => EventSensitivityTypes::Public,
            };
        }
        // Color
        if (isset($so->COLOR)) {
            $to->Color = trim($so->COLOR->getValue());
        }
        // Tag(s)
        if (isset($so->CATEGORIES)) {
            $to->Tags = new EventTagCollection($so->CATEGORIES->getParts());
        }
        // Participant(s)
        foreach (['ORGANIZER', 'ATTENDEE'] as $name) {
            if (isset($so->$name)) {
                foreach ($so->$name as $entry) {
                    $participant = new EventParticipantObject();
                    $participant->id = isset($entry->parameters['X-ID']) ? $entry->parameters['X-ID']->getValue() : UUID::v4();
                    $participant->address = !empty($entry->getValue()) ? trim(str_replace('mailto:', '', $entry->getValue())) : null;
                    $participant->name = isset($entry->parameters['CN']) ? $entry->parameters['CN']->getValue() : null;
                    $participant->type = match ($entry->parameters['CUTYPE']?->getValue()) {
                        'GROUP' => EventParticipantTypes::Group,
                        'RESOURCE' => EventParticipantTypes::Resource,
                        'ROOM' => EventParticipantTypes::Location,
                        default => EventParticipantTypes::Individual,
                    };
                    $participant->status = match ($entry->parameters['PARTSTAT']?->getValue()) {
                        'ACCEPTED' => EventParticipantStatusTypes::Accepted,
                        'DECLINED' => EventParticipantStatusTypes::Declined,
                        'TENTATIVE' => EventParticipantStatusTypes::Tentative,
                        default => ($name === 'ORGANIZER') ? EventParticipantStatusTypes::Accepted : EventParticipantStatusTypes::None,
                    };
                    $participant->roles[] = match ($entry->parameters['ROLE']?->getValue()) {
                        'CHAIR' => EventParticipantRoleTypes::Chair,
                        'OPT-PARTICIPANT' => EventParticipantRoleTypes::Optional,
                        'NON-PARTICIPANT' => EventParticipantRoleTypes::Informational,
                        default => ($name === 'ORGANIZER') ? EventParticipantRoleTypes::Owner : EventParticipantRoleTypes::Attendee,
                    };
                    $to->Participants[$participant->id] = $participant;
                }
            }
        }
        // Notifications
        if (isset($so->VALARM)) {
            foreach($so->VALARM as $entry) {
                $notification = new EventNotificationObject();
                $notification->Type = match ($entry->ACTION?->getValue()) {
                    'EMAIL' => EventNotificationTypes::Email,
                    'AUDIO' => EventNotificationTypes::Audible,
                    default => EventNotificationTypes::Visual,
                };
                if (isset($entry->TRIGGER->parameters['VALUE'])) {
                    $notification->Pattern = EventNotificationPatterns::Absolute;
                    $notification->When = new DateTime($entry->TRIGGER->parameters['VALUE']->getValue());
                
                }
                if (isset($entry->TRIGGER->parameters['RELATED'])) {
                    $notification->Pattern = EventNotificationPatterns::Relative;
                    $notification->Anchor = match ($entry->TRIGGER->parameters['RELATED']->getValue()) {
                        'END' => EventNotificationAnchorTypes::End,
                        default => EventNotificationAnchorTypes::Start,
                    };
                    $notification->Offset = $this->fromDurationPeriod($entry->TRIGGER->getValue());
                }
                
            }
        }
        // Occurrence
        if (isset($so->RRULE)) {
            foreach($so->RRULE as $entry) {
                $pattern = new EventOccurrenceObject();
                $parts = $so->RRULE->getParts();
                if (isset($parts['FREQ'])) {
                    $pattern->Precision = match ($parts['FREQ']) {
                        'YEARLY' => EventOccurrencePrecisionTypes::Yearly,
                        'MONTHLY' => EventOccurrencePrecisionTypes::Monthly,
                        'WEEKLY' => EventOccurrencePrecisionTypes::Weekly,
                        'DAILY' => EventOccurrencePrecisionTypes::Daily,
                        'HOURLY' => EventOccurrencePrecisionTypes::Hourly,
                        'MINUTELY' => EventOccurrencePrecisionTypes::Minutely,
                        'SECONDLY' => EventOccurrencePrecisionTypes::Secondly,
                    };
                }
                if (isset($parts['INTERVAL'])) {
                    $pattern->Interval = (int)$parts['INTERVAL'];
                }
                if (isset($parts['COUNT'])) {
                    $pattern->Iterations = (int)$parts['COUNT'];
                }
                if (isset($parts['UNTIL'])) {
                    $pattern->Concludes = new DateTime($parts['UNTIL']);
                }
                if (isset($parts['BYDAY'])) {
                    if (is_array($parts['BYDAY'])) {
                        $pattern->OnDayOfWeek = $parts['BYDAY'];
                    }
                    else {
                        $pattern->OnDayOfWeek = [$parts['BYDAY']];
                    }
                }
                if (isset($parts['BYMONTH'])) {
                    if (is_array($parts['BYMONTH'])) {
                        $pattern->OnMonthOfYear = $parts['BYMONTH'];
                    }
                    else {
                        $pattern->OnMonthOfYear = [$parts['BYMONTH']];
                    }
                }
                if (isset($parts['BYMONTHDAY'])) {
                    if (is_array($parts['BYMONTHDAY'])) {
                        $pattern->OnDayOfMonth = $parts['BYMONTHDAY'];
                    }
                    else {
                        $pattern->OnDayOfMonth = [$parts['BYMONTHDAY']];
                    }
                }
                if (isset($parts['BYYEARDAY'])) {
                    if (is_array($parts['BYYEARDAY'])) {
                        $pattern->OnDayOfYear = $parts['BYYEARDAY'];
                    }
                    else {
                        $pattern->OnDayOfYear = [$parts['BYYEARDAY']];
                    }
                }
                if (isset($parts['BYSETPOS'])) {
                    if (is_array($parts['BYSETPOS'])) {
                        $pattern->OnPosition = $parts['BYSETPOS'];
                    }
                    else {
                        $pattern->OnPosition = [$parts['BYSETPOS']];
                    }
                }
                $to->OccurrencePatterns[] = $pattern;
            }
        }
        // Attachment(s)
        /*
        if (isset($so->ATTACH)) {
            foreach($so->ATTACH as $entry) {
                if (isset($entry->parameters['X-NC-FILE-ID'])) {
                    $fs = 'D';
                    $fi = $entry->parameters['X-NC-FILE-ID']->getValue();
                    $fn = $entry->parameters['FILENAME']->getValue();
                    $ft = $entry->parameters['FMTTYPE']->getValue();
                    $fd = $entry->parameters['FILENAME']->getValue();

                    $to->addAttachment(
                        $fs,
                        $fi,
                        $fn,
                        $ft,
                        'B',
                        null,
                        $fd
                    );
                }
            }
        }
        */
		// return event object
		return $to;
        
    }

    /**
     * Convert event object to vevent object
     * 
     * @since Release 1.0.0
     * 
	 * @param EventObject $so
	 * 
	 * @return VEvent
	 */
    public function fromEventObject(EventObject $so): VEvent{

        // construct target object
        $to = (new \Sabre\VObject\Component\VCalendar())->createComponent('VEVENT');
        // UID
        if ($so->UUID) {
            $to->UID->setValue($so->UUID);
        } else {
            $to->add('UUID', $so->UUID);
        }
        // creation date
        if ($so->CreatedOn) {
            $to->add('DTSTAMP',$so->CreatedOn->format($this->DateFormatUTC));
            $to->add('CREATED',$so->CreatedOn->format($this->DateFormatUTC));
        }
        // modification date
        if ($so->ModifiedOn) {
            $to->add('LAST-MODIFIED',$so->ModifiedOn->format($this->DateFormatUTC));
        }
        // sequence
        if ($so->Sequence) {
            $to->add('SEQUENCE',$so->Sequence);
        }
        // time zone
        if ($so->TimeZone) {
            $to->add('X-TIMEZONE',$so->TimeZone->getName());
        }
        // Starts Date, Time and Zone
        if ($so->StartsOn) {
            if ($so->Timeless) {
                $to->add('DTSTART', $so->StartsOn->format($this->DateFormatDateOnly));
            } else {
                $to->add('DTSTART', $so->StartsOn->format($this->DateFormatDateTime));
            }
            if ($so->StartsTZ) {
                $to->DTSTART->add('TZID', $so->StartsTZ->getName()); 
            }
            elseif ($so->TimeZone) {
                $to->DTSTART->add('TZID', $so->TimeZone->getName());
            }
        }
        // End Date, Time and Zone
        if ($so->EndsOn) {
            if ($so->Timeless) {
                $to->add('DTEND', $so->EndsOn->format($this->DateFormatDateOnly));
            } else {
                $to->add('DTEND', $so->EndsOn->format($this->DateFormatDateTime));
            }
            if ($so->EndsTZ) {
                $to->DTEND->add('TZID', $so->EndsTZ->getName()); 
            }
            elseif ($so->TimeZone) {
                $to->DTEND->add('TZID', $so->TimeZone->getName());
            }
        }
        // Duration
        if ($so->Duration) {
            //$to->add('DURATION',$so->Duration);
        }
        // Label
        if ($so->Label) {
            $to->add('SUMMARY',$so->Label);
        }
        // Notes
        if ($so->Description) {
            $to->add('DESCRIPTION', $so->Description);
        }
        // Physical Location(s)
		foreach ($so->LocationsPhysical as $id => $entry) {
            $location = $to->add('LOCATION', trim($entry->Name));
            $location->add('X-ID', $id);
		}
        // Availability
        if ($so->Availability) {
            $to->add('TRANSP', match ($so->Availability) {
                EventAvailabilityTypes::Free => 'TRANSPARENT',
                default => 'OPAQUE',
            });
        }
        // Priority
        if ($so->Priority) {
            $to->add('PRIORITY', $so->Priority);
        }
        // Sensitivity
        if (isset($so->Sensitivity)) {
            $to->add('CLASS', match ($so->Sensitivity) {
				EventSensitivityTypes::Private => 'PRIVATE',
				EventSensitivityTypes::Secret => 'CONFIDENTIAL',
				default => 'PUBLIC',
			});
        }
        // Color
        if ($so->Color) {
            $to->add('COLOR', trim($so->Color));
        }
        // Tag(s)
        if (count($so->Tags) > 0) {
            $to->add('CATEGORIES', implode(', ', (array)$so->Tags));
        }
        // Organizer
        /*
        if (isset($to->Organizer) && isset($to->Organizer->Address)) {
            $to->add(
                'ORGANIZER', 
                'mailto:' . $to->Organizer->Address,
                array('CN' => $to->Organizer->Name)
            );
        }
        */
        // Participant(s)
        foreach($so->Participants as $id => $entry) {
            $participant = $to->add('ATTENDEE', 'mailto:' . $entry->address);
            // Participant Type
            $participant->add('CUTYPE',  match ($entry->Type) {
                EventParticipantTypes::Individual => 'INDIVIDUAL',
                EventParticipantTypes::Group => 'GROUP',
                EventParticipantTypes::Resource => 'RESOURCE',
                EventParticipantTypes::Location => 'ROOM',
                default => 'UNKNOWN',
            });
            // Participant Status
            $participant->add('PARTSTAT',  match ($entry->Type) {
                EventParticipantStatusTypes::Accepted => 'ACCEPTED',
                EventParticipantStatusTypes::Declined => 'DECLINED',
                EventParticipantStatusTypes::Tentative => 'TENTATIVE',
                EventParticipantStatusTypes::Delegated => 'DELEGATED',
                default => 'NEEDS-ACTION',
            });
            // Participant Name
            if ($entry->Name) {
                $participant->add('CN', $entry->Name);
            }
        }
        // Notifications
        foreach($so->Notifications as $entry) {
            $notification = $to->add('VALARM');
            // Notifications Type
            $notification->add('ACTION', match ($entry->Type) {
                EventNotificationTypes::Email => 'EMAIL',
                default => 'DISPLAY',
            });
            // Notifications Pattern
            switch ($entry->Pattern) {
                case EventNotificationPatterns::Absolute:
                    $notification->add('VALUE', $entry->When, array());
                    break;
                case EventNotificationPatterns::Relative:
                    $notification->add(
                        'TRIGGER',
                        $this->toDurationPeriod($entry->Offset),
                        ['RELATED' => ($entry->Anchor === EventNotificationAnchorTypes::End) ? 'END' : 'START']
                    );
                    break;
            }
        }

        // Occurrence
        foreach ($so->OccurrencePatterns as $id => $entry) {
            $pattern = [];
            // Occurrence Precision
            $pattern['FREQ'] = match ($entry->Precision) {
                EventOccurrencePrecisionTypes::Yearly => 'YEARLY',
                EventOccurrencePrecisionTypes::Monthly => 'MONTHLY',
                EventOccurrencePrecisionTypes::Weekly => 'WEEKLY',
                EventOccurrencePrecisionTypes::Daily => 'DAILY',
                EventOccurrencePrecisionTypes::Hourly => 'HOURLY',
                EventOccurrencePrecisionTypes::Minutely => 'MINUTELY',
                EventOccurrencePrecisionTypes::Secondly => 'SECONDLY',
            };
            // Occurrence Interval
            if ($entry->Interval) {
                $pattern['INTERVAL'] = $entry->Interval;
            }
            // Occurrence Iterations
            if ($entry->Iterations) {
                $pattern['COUNT'] = $entry->Iterations;
            }
            // Occurrence Conclusion
            if ($entry->Concludes) {
                $pattern['UNTIL'] = $entry->Concludes->format($this->DateFormatUTC);
            }
            // Occurrence Day Of Week
            if (count($entry->OnDayOfWeek) > 0) {
                $pattern['BYDAY'] = implode(',', $entry->OnDayOfWeek);
            }
            // Occurrence Day Of Month
            if (count($entry->OnDayOfMonth) > 0) {
                $pattern['BYMONTHDAY'] = implode(',', $entry->OnDayOfMonth);
            }
            // Occurrence Day Of Year
            if (count($entry->OnDayOfYear) > 0) {
                $pattern['BYYEARDAY'] = implode(',', $entry->OnDayOfYear);
            }
            // Occurrence Week Of Year
            if (count($entry->OnWeekOfYear) > 0) {
                $pattern['BYWEEKNO'] = implode(',', $entry->OnWeekOfYear);
            }
            // Occurrence Month Of Year
            if (count($entry->OnMonthOfYear) > 0) {
                $pattern['BYMONTH'] = implode(',', $entry->OnMonthOfYear);
            }
            // Occurrence Position
            if (count($entry->OnPosition) > 0) {
                $pattern['BYSETPOS'] = implode(',', $entry->OnPosition);
            }

            $to->add('RRULE', $pattern)->add('X-ID', $id);
        }

                // Attachment(s)
        /*
        if (count($so->Attachments) > 0) {
            foreach($so->Attachments as $entry) {
                // Data Store
                if ($entry->Store == 'D' && !empty($entry->Id)) {
                    $p = array();
                    $p['X-NC-FILE-ID'] = $entry->Id;
                    $p['FILENAME'] = $entry->Data;
                    $p['FMTTYPE'] = $entry->Type;
                    $to->add('ATTACH', "/f/" . $entry->Id, $p);
                    unset($p);
                }
                // Referance
                elseif ($entry->Store == 'R' && !empty($entry->Data)) {
                    $p = array();
                    $p['FMTTYPE'] = $entry->Type;
                    $to->add('ATTACH', $entry->Data, $p);
                    unset($p);
                }
                // Enclosed
                elseif (!empty($entry->Data)) {
                    $p = array();
                    $p['FMTTYPE'] = $entry->Type;
                    $p['ENCODING'] = 'BASE64';
                    $p['VALUE'] = 'BINARY';
                    unset($p);
                    if ($entry->Encoding == 'B64') {
                        $to->add(
                            'ATTACH',
                            'X-FILENAME="' . $entry->Name . '":' . $entry->Data,
                            $p
                        );
                    }
                    else {
                        $to->add(
                            'ATTACH',
                            'X-FILENAME="' . $entry->Name . '":' .  base64_encode($entry->Data),
                            $p
                        );
                    }
                }
                
            }
        }
        */

        return $to;

    }

    /**
     * convert local duration period to event object date interval
	 * 
     * @since Release 1.0.0
     * 
	 * @param sting $period
	 * 
	 * @return DateInterval
	 */
    private function fromDurationPeriod(string $period): DateInterval {
		
        // evaluate if period is negative
		if (str_contains($period, '-P')) {
            $period = trim($period, '-');
            $period = new DateInterval($period);
            $period->invert = 1;
            // return date interval object
            return $period;
        }
        else {
            // return date interval object
            return new DateInterval($period);
        }
		
	}

    /**
     * convert event object date interval to local duration period
	 * 
     * @since Release 1.0.0
     * 
	 * @param DateInterval $period
	 * 
	 * @return string
	 */
	private function toDurationPeriod(DateInterval $period): string {

        return match (true) {
            ($period->y > 0) => $period->format("%rP%yY%mM%dDT%hH%iM"),
            ($period->m > 0) => $period->format("%rP%mM%dDT%hH%iM"),
            ($period->d > 0) => $period->format("%rP%dDT%hH%iM"),
            ($period->h > 0) => $period->format("%rPT%hH%iM"),
            default => $period->format("%rPT%iM")
        };

	}

}
