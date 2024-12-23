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
use OC\Files\Node\LazyUserFolder;
use OCA\JMAPC\Objects\Contact\ContactObject;
use OCA\JMAPC\Objects\ContactCollection;
use OCA\JMAPC\Store\ContactEntity;
use OCA\JMAPC\Store\ContactStore;

use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCard;

class LocalContactsService {
	
    protected string $DateFormatUTC = 'Ymd\THis\Z';
    protected string $DateFormatDateTime = 'Ymd\THis';
    protected string $DateFormatDateOnly = 'Ymd';
	protected ContactStore $_Store;
	protected ?DateTimeZone $SystemTimeZone = null;
	protected ?DateTimeZone $UserTimeZone = null;
	protected string $UserAttachmentPath = '';
	protected ?LazyUserFolder $FileStore = null;

	public function __construct () {
	}
    
    public function initialize(ContactStore $Store) {

		$this->_Store = $Store;

	}

	/**
     * retrieve collection from local storage
     * 
	 * @param string $cid            Collection ID
	 * 
	 * @return ContactCollection  ContactCollection on success / null on fail
	 */
	public function collectionFetch(int $cid): ?ContactCollection {

        // retrieve object properties
        $ce = $this->_Store->collectionFetch($cid);
        // evaluate if object properties where retrieve
        if (is_array($ce) && count($ce) > 0) {
            // construct object and return
            return new ContactCollection(
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
	 * @return ContactObject|null
	 */
	public function entityFetch(int $id): ContactObject|null {

        // retrieve entity object
        $eo = $this->_Store->entityFetch($id);
        // evaluate if entity was retrieved
        if ($eo instanceof ContactEntity) {
            return $this->fromContactEntity($eo);
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
	 * @return ContactObject|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ContactObject|null {

        // retrieve entity object
        $eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
		// evaluate if entity was retrieved
        if ($eo instanceof ContactEntity) {
            return $this->fromContactEntity($eo);
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
     * @param ContactObject $so       source object
	 * 
	 * @return object               Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityCreate(string $uid, int $sid, int $cid, ContactObject $so): ?object {

        // convert event object to data store entity
        $eo = $this->toContactEntity(
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
     * @param ContactObject $so       source object
	 * 
	 * @return object               Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityModify(string $uid, int $sid, int $cid, int $eid, ContactObject $so): ?object {

        // convert event object to data store entity
        $eo = $this->toContactEntity(
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
        if ($eo instanceof ContactEntity) {
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
	 * @param ContactEntity $so
     * @param array<string,mixed>
	 * 
	 * @return ContactObject
	 */
	public function fromContactEntity(ContactEntity $so, array $additional = []): ContactObject {

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
	 * @param ContactObject $so
     * @param array<string,mixed>
	 * 
	 * @return ContactEntity
	 */
	public function toContactEntity(ContactObject $so, array $additional = []): ContactEntity {

        // construct entity
        $to = new ContactEntity();
        // convert source object to entity
        $to->setData("BEGIN:VCALENDAR\nVERSION:2.0\n" . $this->fromContactObject($so)->serialize() . "\nEND:VCALENDAR");
        $to->setUuid($so->UUID);
        $to->setSignature(md5($to->getData()));
        $to->setCcid($so->CCID);
        $to->setCeid($so->CEID);
        $to->setCesn($so->CESN);
        $to->setLabel($so->Label);
        $to->setDescription($so->Description);
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
	 * @param VCard $so
	 * 
	 * @return ContactObject
	 */
	public function fromVEvent(VCard $so): ContactObject {
		
        // construct target object
		$to = new ContactObject();
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
     * Convert event object to VCard object
     * 
     * @since Release 1.0.0
     * 
	 * @param ContactObject $so
	 * 
	 * @return VCard
	 */
    public function toVCard(ContactObject $so): VCard {

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
     * convert vcard object to contact object
     * 
	 * @param VCard $vo - source object
	 * 
	 * @return ContactObject converted object
	 */
	public function toContactObject(VCard $vo): ContactObject {

		// construct contact object
		$co = new ContactObject();
        // UUID
        if (isset($vo->UID)) {
            $co->UUID = $this->sanitizeString($vo->UID->getValue());
        }
        // Label
        if (isset($vo->FN)) {
            $co->Label = $this->sanitizeString($vo->FN->getValue());
        }
		// Name
        if (isset($vo->N)) {
            $p = $vo->N->getParts();
            $co->Name->Last = $this->sanitizeString($p[0]);
            $co->Name->First = $this->sanitizeString($p[1]);
            $co->Name->Other = $this->sanitizeString($p[2]);
            $co->Name->Prefix = $this->sanitizeString($p[3]);
            $co->Name->Suffix = $this->sanitizeString($p[4]);
            $co->Name->PhoneticLast = $this->sanitizeString($p[6]);
            $co->Name->PhoneticFirst = $this->sanitizeString($p[7]);
            $co->Name->Aliases = $this->sanitizeString($p[5]);
            unset($p);
        }
        // Aliases
        if (isset($vo->NICKNAME)) {
            if (empty($co->Name->Aliases)) {
                $co->Name->Aliases .= $this->sanitizeString($vo->NICKNAME->getValue());
            }
            else {
                $co->Name->Aliases .= ' ' . $this->sanitizeString($vo->NICKNAME->getValue());
            }
        }
        // Photo
        if (isset($vo->PHOTO)) {
            $p = $vo->PHOTO->getValue();
            if (str_starts_with($p, 'data:')) {
                $p = explode(';', $p);
                if (count($p) == 2) {
                    $p[0] = explode(':', $p[0]);
                    $p[1] = explode(',', $p[1]);
                    $co->Photo->Type = 'data';
                    $co->Photo->Data = $vo->UID;
                    $co->addAttachment(
                        $vo->UID,
                        $vo->UID . '.' . \OCA\JMAPC\Utile\MIME::toExtension($p[0][1]),
                        $p[0][1],
                        'B64',
                        'CP',
                        null,
                        $p[1][1]
                    );
                }
            } elseif (str_starts_with($p, 'uri:')) {
                $co->Photo->Type = 'uri';
                $co->Photo->Data = $this->sanitizeString(substr($p,4));
            }
            unset($p);
        }
        // Gender
        if (isset($vo->GENDER)) {
            $co->Gender = $this->sanitizeString($vo->GENDER->getValue());
        }
        // Birth Day
        if (isset($vo->BDAY)) {
            $co->BirthDay =  new DateTime($vo->BDAY->getValue());
        }
        // Anniversary Day
        if (isset($vo->ANNIVERSARY)) {
            $co->NuptialDay =  new DateTime($vo->ANNIVERSARY->getValue());
        }
        // Address(es)
        if (isset($vo->ADR)) {
            foreach($vo->ADR as $entry) {
                $type  = $entry->parameters()['TYPE']->getValue();
                [$pob, $unit, $street, $locality, $region, $code, $country] = $entry->getParts();
                $co->addAddress(
                    strtoupper($type),
                    $this->sanitizeString($street),
                    $this->sanitizeString($locality),
                    $this->sanitizeString($region),
                    $this->sanitizeString($code),
                    $this->sanitizeString($country)
                );
            }
            unset($type, $pob, $unit, $street, $locality, $region, $code, $country);
        }
        // Phone(s)
        if (isset($vo->TEL)) {
            foreach($vo->TEL as $entry) {
                [$primary, $secondary] = explode(',', trim($entry->parameters()['TYPE']->getValue()));
                $co->addPhone(
                    $primary,
                    $secondary, 
                    $this->sanitizeString($entry->getValue())
                );
            }
            unset($primary, $secondary);
        }
        // Email(s)
        if (isset($vo->EMAIL)) {
            foreach($vo->EMAIL as $entry) {
                $co->addEmail(
                    strtoupper(trim($entry->parameters()['TYPE']->getValue())), 
                    $this->sanitizeString($entry->getValue())
                );
            }
        }
        // IMPP(s)
        if (isset($vo->IMPP)) {
            foreach($vo->IMPP as $entry) {
                $co->addIMPP(
                    strtoupper(trim($entry->parameters()['TYPE']->getValue())), 
                    $this->sanitizeString($entry->getValue())
                );
            }
        }
        // Time Zone
        if (isset($vo->TZ)) {
            $co->TimeZone = $this->sanitizeString($vo->TZ->getValue());
        }
        // Geolocation
        if (isset($vo->GEO)) {
            $co->Geolocation = $this->sanitizeString($vo->GEO->getValue());
        }
        // Manager
		if (isset($vo->{'X-MANAGERSNAME'})) {
			$co->Manager = $this->sanitizeString($vo->{'X-MANAGERSNAME'}->getValue());
		}
        // Assistant
		if (isset($vo->{'X-ASSISTANTNAME'})) {
			$co->Assistant = $this->sanitizeString($vo->{'X-ASSISTANTNAME'}->getValue());
		}
        // Occupation Organization
        if (isset($vo->ORG)) {
			$co->Occupation->Organization = $this->sanitizeString($vo->ORG->getValue());
		}
		// Occupation Title
        if (isset($vo->TITLE)) { 
			$co->Occupation->Title = $this->sanitizeString($vo->TITLE->getValue()); 
		}
		// Occupation Role
		if (isset($vo->ROLE)) {
			$co->Occupation->Role = $this->sanitizeString($vo->ROLE->getValue());
		}
		// Occupation Logo
		if (isset($vo->LOGO)) {
			$co->Occupation->Logo = $this->sanitizeString($vo->LOGO->getValue());
		}
                
        // Relation
        if (isset($vo->RELATED)) {
            $co->addRelation(
				strtoupper(trim($vo->RELATED->parameters()['TYPE']->getValue())),
				sanitizeString($vo->RELATED->getValue())
			);
        }
        // Tag(s)
        if (isset($vo->CATEGORIES)) {
            foreach($vo->CATEGORIES->getParts() as $entry) {
                $co->addTag(
                    $this->sanitizeString($entry)
                );
            }
        }
        // Notes
        if (isset($vo->NOTE)) {
            if (!empty(trim($vo->NOTE->getValue()))) {
                $co->Notes = $this->sanitizeString($vo->NOTE->getValue());
            }
        }
        // Sound
        if (isset($vo->SOUND)) {
            $co->Sound = $this->sanitizeString($vo->SOUND->getValue());
        }
        // URL / Website
        if (isset($vo->URL)) {
            $co->URI = $this->sanitizeString($vo->URL->getValue());
        }

        // return contact object
		return $co;

    }

    /**
     * Convert contact object to vcard object
     * 
	 * @param ContactObject $co - source object
	 * 
	 * @return VCard converted object
	 */
    public function fromContactObject(ContactObject $co): VCard {

        // construct vcard object
        $vo = new VCard();
        // UID
        if (isset($co->UUID)) {
            $vo->UID->setValue($co->UUID);
        } else {
            $vo->UID->setValue(\OCA\JMAPC\Utile\UUID::v4());
        }
        // Label
        if (isset($co->Label)) {
            $vo->add('FN', $co->Label);
        }
        // Name
        if (isset($co->Name)) {
            $vo->add(
                'N',
                array(
                    $co->Name->Last,
                    $co->Name->First,
                    $co->Name->Other,
                    $co->Name->Prefix,
                    $co->Name->Suffix,
                    $co->Name->PhoneticLast,
                    $co->Name->PhoneticFirst,
                    $co->Name->Aliases
            ));
        }
        // Photo
        if (isset($co->Photo)) {
            if ($co->Photo->Type == 'uri') {
                $vo->add(
                    'PHOTO',
                    'uri:' . $co->Photo->Data
                );
            } elseif ($co->Photo->Type == 'data') {
                $k = array_search($co->Photo->Data, array_column($co->Attachments, 'Id'));
                if ($k !== false) {
                    switch ($co->Attachments[$k]->Encoding) {
                        case 'B':
                            $vo->add(
                                'PHOTO',
                                'data:' . $co->Attachments[$k]->Type . ';base64,' . base64_encode($co->Attachments[$k]->Data)
                            );
                            break;
                        case 'B64':
                            $vo->add(
                                'PHOTO',
                                'data:' . $co->Attachments[$k]->Type . ';base64,' . $co->Attachments[$k]->Data
                            );
                            break;
                    }
                }
            }
        }
        // Gender
        if (isset($co->Gender)) {
            $vo->add(
                'GENDER',
                $co->Gender
            );
        }
        // Birth Day
        if (isset($co->BirthDay)) {
            $vo->add(
                'BDAY',
                $co->BirthDay->format('Y-m-d\TH:i:s\Z')
            );
        }
        // Anniversary Day
        if (isset($co->NuptialDay)) {
            $vo->add(
                'ANNIVERSARY',
                $co->NuptialDay->format('Y-m-d\TH:i:s\Z')
            );
        }
        // Address(es)
        if (count($co->Address) > 0) {
            foreach ($co->Address as $entry) {
                $vo->add('ADR',
                    array(
                        '',
                        '',
                        $entry->Street,
                        $entry->Locality,
                        $entry->Region,
                        $entry->Code,
                        $entry->Country,
                    ),
                    array (
                        'TYPE'=>$entry->Type
                    )
                );
            }
        }
        // Phone(s)
        if (count($co->Phone) > 0) {
            foreach ($co->Phone as $entry) {
                $vo->add(
                    'TEL', 
                    $entry->Number,
                    array (
                        'TYPE'=> (isset($entry->SubType)) ? ($entry->Type . ',' . $entry->SubType) : $entry->Type
                    )
                );
            }
        }
        // Email(s)
        if (count($co->Email) > 0) {
            foreach ($co->Email as $entry) {
                $vo->add(
                    'EMAIL', 
                    $entry->Address,
                    array (
                        'TYPE'=>$entry->Type
                    )
                );
            }
        }
        // IMPP(s)
        if (count($co->IMPP) > 0) {
            foreach ($co->IMPP as $entry) {
                $vo->add(
                    'IMPP', 
                    $entry->Address,
                    array (
                        'TYPE'=>$entry->Type
                    )
                );
            }
        }
        // Time Zone
        if (isset($co->TimeZone)) {
            $vo->add(
                'TZ',
                $co->TimeZone
            );
        }
        // Geolocation
        if (isset($co->Geolocation)) {
            $vo->add(
                'GEO',
                $co->Geolocation
            );
        }
        // Manager Name
		if (!empty($co->Manager)) {
            $vo->add(
                'X-MANAGERSNAME',
                $co->Manager
            );
		}
        // Assistant Name
		if (!empty($co->Assistant)) {
            $vo->add(
                'X-ASSISTANTNAME',
                $co->Assistant
            );
		}
        // Occupation Organization
        if (isset($co->Occupation->Organization)) {
            $vo->add(
                'ORG',
                $co->Occupation->Organization
            );
        }
        // Occupation Title
        if (isset($co->Occupation->Title)) {
            $vo->add(
                'TITLE',
                $co->Occupation->Title
            );
        }
        // Occupation Role
        if (isset($co->Occupation->Role)) {
            $vo->add(
                'ROLE',
                $co->Occupation->Role
            );
        }
        // Occupation Logo
        if (isset($co->Occupation->Logo)) {
            $vo->add(
                'LOGO',
                $co->Occupation->Logo
            );
        }
        // Relation(s)
        if (count($co->Relation) > 0) {
            foreach ($co->Relation as $entry) {
                $vo->add(
                    'RELATED', 
                    $entry->Value,
                    array (
                        'TYPE'=>$entry->Type
                    )
                );
            }
        }
        // Tag(s)
        if (count($co->Tags) > 0) {
            $vo->add('CATEGORIES', $co->Tags);
        }
        // Notes
        if (isset($co->Notes)) {
            $vo->add(
                'NOTE',
                $co->Notes
            );
        }
        // Sound
        if (isset($co->Sound)) {
            $vo->add(
                'SOUND',
                $co->Sound
            );
        }
        // URL / Website
        if (isset($co->URI)) {
            $vo->add(
                'URL',
                $co->URI
            );
        }

        // return vcard object
        return $vo;

    }
    
    public function sanitizeString($value): string|null {

        // remove white space
        $value = trim($value);
        // return value or null
        return $value === '' ? null : $value;
        
    }

}
