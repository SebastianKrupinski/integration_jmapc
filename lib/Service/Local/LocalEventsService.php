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

namespace OCA\JMAPC\Service\Local;

use DateInterval;
use Datetime;
use DateTimeZone;
use OC\Files\Node\LazyUserFolder;
use OCA\DAV\CalDAV\EventReader;
use OCA\JMAPC\Objects\Event\EventAvailabilityTypes;
use OCA\JMAPC\Objects\Event\EventCollectionObject;
use OCA\JMAPC\Objects\Event\EventLocationPhysicalObject;
use OCA\JMAPC\Objects\Event\EventMutationObject;
use OCA\JMAPC\Objects\Event\EventNotificationAnchorTypes;
use OCA\JMAPC\Objects\Event\EventNotificationObject;
use OCA\JMAPC\Objects\Event\EventNotificationPatterns;
use OCA\JMAPC\Objects\Event\EventNotificationTypes;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\Event\EventOccurrenceObject;
use OCA\JMAPC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\JMAPC\Objects\Event\EventParticipantObject;
use OCA\JMAPC\Objects\Event\EventParticipantRoleTypes;
use OCA\JMAPC\Objects\Event\EventParticipantStatusTypes;
use OCA\JMAPC\Objects\Event\EventParticipantTypes;
use OCA\JMAPC\Objects\Event\EventSensitivityTypes;
use OCA\JMAPC\Objects\Event\EventTagCollection;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Store\Local\CollectionEntity;
use OCA\JMAPC\Store\Local\EventEntity;
use OCA\JMAPC\Store\Local\EventStore;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

class LocalEventsService {
	protected string $ProductionId = '-//IDN nextcloud.com//JMAP Client//EN';
	protected string $DateFormatUTC = 'Ymd\THis\Z';
	protected string $DateFormatDateTime = 'Ymd\THis';
	protected string $DateFormatDateOnly = 'Ymd';
	protected EventStore $_Store;
	protected ?DateTimeZone $SystemTimeZone = null;
	protected ?DateTimeZone $UserTimeZone = null;
	protected string $UserAttachmentPath = '';
	protected ?LazyUserFolder $FileStore = null;

	public function __construct() {
	}

	public function initialize(EventStore $Store) {

		$this->_Store = $Store;

	}

	/**
	 * retrieve collection from local storage
	 *
	 * @param int $cid Collection ID
	 *
	 * @return EventCollectionObject|null
	 */
	public function collectionFetch(int $cid): ?EventCollectionObject {

		// retrieve collection properties
		$co = $this->_Store->collectionFetch($cid);
		// evaluate if properties where retrieve
		if ($co instanceof CollectionEntity) {
			// construct object and return
			return new EventCollectionObject(
				(string)$co->getId(),
				$co->getLabel(),
				null,
				null,
				$co->getVisible(),
				$co->getColor()
			);
		} else {
			// return nothing
			return null;
		}

	}

	/**
	 * delete collection from local storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
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
	 * @param int $cid collection id
	 *
	 * @return array collection of entities
	 */
	public function entityList(int $cid, string $particulars): array {

		return $this->_Store->entityListByCollection($cid);

	}

	/**
	 * retrieve the differences for specific collection from a specific point from local storage
	 *
	 * @param string $uid user id
	 * @param int $cid collection id
	 * @param string $signature collection signature
	 *
	 * @return array collection of differences
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
	 * @param int $id entity id
	 *
	 * @return EventObject|null
	 */
	public function entityFetch(int $id): ?EventObject {

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
	 * @param int $cid collection id
	 * @param string $ccid correlation collection id
	 * @param string $ceid correlation entity id
	 *
	 * @return EventObject|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ?EventObject {

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
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param int $cid collection id
	 * @param EventObject $so source object
	 *
	 * @return object Status Object - item id, item uuid, item state token / Null - failed to create
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
			return (object)['ID' => $eo->getId(), 'Signature' => $eo->getSignature()];
		} else {
			return null;
		}

	}

	/**
	 * modify entity in local storage
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param int $cid collection id
	 * @param int $eid entity id
	 * @param EventObject $so source object
	 *
	 * @return object Status Object - item id, item uuid, item state token / Null - failed to create
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
			return (object)['ID' => $eo->getId(), 'Signature' => $eo->getSignature()];
		} else {
			return null;
		}

	}

	/**
	 * delete entity from local storage
	 *
	 * @param int $eid entity id
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
	 * @param int $cid collection id
	 * @param string $ccid correlation collection id
	 * @param string $ceid correlation entity id
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
		$to = $this->toEventObject($vObject);
		$to->ID = (string)$so->getId();
		$to->CID = (string)$so->getCid();
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
		$vo = $this->fromEventObject($so);
		// convert source object to entity
		$to->setData($vo->serialize());
		$to->setUuid($so->UUID);
		$to->setSignature($this->generateSignature($so));
		$to->setCcid($so->CCID);
		$to->setCeid($so->CEID);
		$to->setCesn($so->CESN);
		$to->setLabel($so->Label);
		$to->setDescription($so->Description);
		$to->setStartson($so->StartsOn->setTimezone(new DateTimeZone('UTC'))->format('U'));
		if ($so->OccurrencePatterns > 0) {
			$eventReader = new EventReader($vo, $so->UUID);
			if ($eventReader->recurringConcludes()) {
				$to->setEndson($eventReader->recurringConcludesOn()->setTimezone(new DateTimeZone('UTC'))->format('U'));
			} else {
				$to->setEndson(2147483647);
			}
		} else {
			$to->setEndson($so->EndsOn->setTimezone(new DateTimeZone('UTC'))->format('U'));
		}

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
	 * @param VCalendar $so
	 *
	 * @return EventObject
	 */
	public function toEventObject(VCalendar $so): EventObject {

		// construct target object
		$do = new EventObject();
		// Origin
		$do->Origin = OriginTypes::Internal;
		// universal id
		if (isset($so->UID)) {
			$do->UUID = trim($so->UID->getValue());
		}

		foreach ($so->getComponents() as $vComponent) {
			if ($vComponent->name !== 'VEVENT') {
				continue;
			}
			if ($vComponent->{'RECURRENCE-ID'}) {
				$id = $vComponent->{'RECURRENCE-ID'}->getDateTime();
				$instance = new EventMutationObject();
				$instance->mutationId = $id;
				$this->toEventInstanceObject($vComponent, $instance, $so->VEVENT);
				$do->OccurrenceMutations[$id->format('Y-m-d\TH:i:s')] = $instance;
			} else {
				$do = $this->toEventInstanceObject($vComponent, $do);
			}
		}

		// return event object
		return $do;

	}

	public function toEventInstanceObject(VEvent $so, EventObject|EventMutationObject $do, ?VEvent $bo = null): EventObject|EventMutationObject {

		// creation date time
		if (isset($so->CREATED)) {
			$do->CreatedOn = $so->CREATED->getDateTime();
		}
		// modification date time
		if (isset($so->{'LAST-MODIFIED'})) {
			$do->ModifiedOn = $so->{'LAST-MODIFIED'}->getDateTime();
		}
		// sequence
		if (isset($so->SEQUENCE)) {
			$do->Sequence = (int)$so->SEQUENCE->getValue();
		}
		// time zone
		if (isset($so->{'X-TIMEZONE'})) {
			$do->TimeZone = new DateTimeZone($so->{'X-TIMEZONE'}->getValue());
		}
		// Starts Date/Time
		// Starts Time Zone
		if (isset($so->DTSTART)) {
			$do->StartsOn = $so->DTSTART->getDateTime();
			$do->StartsTZ = $do->StartsOn->getTimezone();
		}
		// Ends Date/Time
		// Ends Time Zone
		if (isset($so->DTEND)) {
			$do->EndsOn = $so->DTEND->getDateTime();
			$do->EndsTZ = $do->EndsOn->getTimezone();
		}
		// duration
		if (isset($so->DURATION)) {
			$do->Duration = $so->DURATION->getDateInterval();
		}
		// label
		if (isset($so->SUMMARY) && !($bo && isset($bo->SUMMARY) && $bo->SUMMARY->getValue() === $so->SUMMARY->getValue())) {
			$do->Label = trim($so->SUMMARY->getValue());
		}
		// description
		if (isset($so->DESCRIPTION) && !($bo && isset($bo->SUMMARY) && $bo->SUMMARY->getValue() === $so->SUMMARY->getValue())) {
			if (!empty(trim($so->DESCRIPTION->getValue()))) {
				$do->Description = trim($so->DESCRIPTION->getValue());
			}
		}
		// location
		if (isset($so->LOCATION)) {
			foreach ($so->LOCATION as $id => $entry) {
				$parameters = $entry->parameters();
				$entity = new EventLocationPhysicalObject();
				$entity->Id = $parameters['X-ID']?->getValue();
				$entity->Name = trim($entry->getValue());
				//$location->Description = $entry->description();
				$do->LocationsPhysical[$entity->Id] = $entity;
			}
		}
		// availability
		if (isset($so->TRANSP)) {
			$do->Availability = match (strtoupper($so->TRANSP->getValue())) {
				'FREE' => EventAvailabilityTypes::Free,
				default => EventAvailabilityTypes::Busy,
			};
		}
		// priority
		if (isset($so->PRIORITY)) {
			$do->Priority = (int)trim($so->PRIORITY->getValue());
		}
		// sensitivity
		if (isset($so->CLASS)) {
			$do->Sensitivity = match (strtoupper($so->CLASS->getValue())) {
				'PRIVATE' => EventSensitivityTypes::Private,
				'CONFIDENTIAL' => EventSensitivityTypes::Secret,
				default => EventSensitivityTypes::Public,
			};
		}
		// color
		if (isset($so->COLOR)) {
			$do->Color = trim($so->COLOR->getValue());
		}
		// tag(s)
		if (isset($so->CATEGORIES)) {
			$do->Tags = new EventTagCollection($so->CATEGORIES->getParts());
		}
		// participant(s)
		foreach (['ORGANIZER', 'ATTENDEE'] as $name) {
			if (isset($so->$name)) {
				foreach ($so->$name as $entry) {
					$parameters = $entry->parameters();
					$entity = new EventParticipantObject();
					$entity->Address = !empty($entry->getValue()) ? trim(str_replace('mailto:', '', $entry->getValue())) : null;
					$entity->Id = $parameters['X-ID']?->getValue();
					$entity->Name = $parameters['CN']?->getValue() ?? null;
					$entity->Type = match ($parameters['CUTYPE']?->getValue()) {
						'GROUP' => EventParticipantTypes::Group,
						'RESOURCE' => EventParticipantTypes::Resource,
						'ROOM' => EventParticipantTypes::Location,
						default => EventParticipantTypes::Individual,
					};
					$entity->Status = match ($parameters['PARTSTAT']?->getValue()) {
						'ACCEPTED' => EventParticipantStatusTypes::Accepted,
						'DECLINED' => EventParticipantStatusTypes::Declined,
						'TENTATIVE' => EventParticipantStatusTypes::Tentative,
						default => ($name === 'ORGANIZER') ? EventParticipantStatusTypes::Accepted : EventParticipantStatusTypes::None,
					};
					$entity->Roles[] = match ($parameters['ROLE']?->getValue()) {
						'CHAIR' => EventParticipantRoleTypes::Chair,
						'OPT-PARTICIPANT' => EventParticipantRoleTypes::Optional,
						'NON-PARTICIPANT' => EventParticipantRoleTypes::Informational,
						default => ($name === 'ORGANIZER') ? EventParticipantRoleTypes::Owner : EventParticipantRoleTypes::Attendee,
					};
					$do->Participants[$entity->Id] = $entity;
				}
			}
		}
		// notifications
		if (isset($so->VALARM)) {
			foreach ($so->VALARM as $entry) {
				$entity = new EventNotificationObject();
				$entity->Id = $entry->{'X-ID'}->getValue();
				$entity->Type = match ($entry->ACTION?->getValue()) {
					'EMAIL' => EventNotificationTypes::Email,
					'AUDIO' => EventNotificationTypes::Audible,
					default => EventNotificationTypes::Visual,
				};
				if (isset($entry->TRIGGER?->parameters()['VALUE'])) {
					$entity->Pattern = EventNotificationPatterns::Absolute;
					$entity->When = new DateTime($entry->TRIGGER->parameters()['VALUE']->getValue());
				}
				if (isset($entry->TRIGGER?->parameters()['RELATED'])) {
					$entity->Pattern = EventNotificationPatterns::Relative;
					$entity->Anchor = match ($entry->TRIGGER->parameters()['RELATED']->getValue()) {
						'END' => EventNotificationAnchorTypes::End,
						default => EventNotificationAnchorTypes::Start,
					};
					$entity->Offset = $entry->TRIGGER->getDateInterval();
				}
				$do->Notifications[$entity->Id] = $entity;
			}
		}
		// occurrence
		if (isset($so->RRULE)) {
			foreach ($so->RRULE as $entry) {
				$entity = new EventOccurrenceObject();
				$parts = $so->RRULE->getParts();
				if (isset($parts['FREQ'])) {
					$entity->Precision = match ($parts['FREQ']) {
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
					$entity->Interval = (int)$parts['INTERVAL'];
				}
				if (isset($parts['COUNT'])) {
					$entity->Iterations = (int)$parts['COUNT'];
				}
				if (isset($parts['UNTIL'])) {
					$entity->Concludes = new DateTime($parts['UNTIL']);
				}
				if (isset($parts['BYDAY'])) {
					if (is_array($parts['BYDAY'])) {
						$entity->OnDayOfWeek = $parts['BYDAY'];
					} else {
						$entity->OnDayOfWeek = [$parts['BYDAY']];
					}
				}
				if (isset($parts['BYMONTH'])) {
					if (is_array($parts['BYMONTH'])) {
						$entity->OnMonthOfYear = $this->convertToInt($parts['BYMONTH']);
					} else {
						$entity->OnMonthOfYear = $this->convertToInt([$parts['BYMONTH']]);
					}
				}
				if (isset($parts['BYMONTHDAY'])) {
					if (is_array($parts['BYMONTHDAY'])) {
						$entity->OnDayOfMonth = $this->convertToInt($parts['BYMONTHDAY']);
					} else {
						$entity->OnDayOfMonth = $this->convertToInt([$parts['BYMONTHDAY']]);
					}
				}
				if (isset($parts['BYYEARDAY'])) {
					if (is_array($parts['BYYEARDAY'])) {
						$entity->OnDayOfYear = $this->convertToInt($parts['BYYEARDAY']);
					} else {
						$entity->OnDayOfYear = $this->convertToInt([$parts['BYYEARDAY']]);
					}
				}
				if (isset($parts['BYSETPOS'])) {
					if (is_array($parts['BYSETPOS'])) {
						$entity->OnPosition = $this->convertToInt($parts['BYSETPOS']);
					} else {
						$entity->OnPosition = $this->convertToInt([$parts['BYSETPOS']]);
					}
				}
				$do->OccurrencePatterns[] = $entity;
			}
		}
		// Attachment(s)
		/*
		if (isset($so->ATTACH)) {
			foreach($so->ATTACH as $entry) {
				if (isset($parameters['X-NC-FILE-ID'])) {
					$fs = 'D';
					$fi = $parameters['X-NC-FILE-ID']->getValue();
					$fn = $parameters['FILENAME']->getValue();
					$ft = $parameters['FMTTYPE']->getValue();
					$fd = $parameters['FILENAME']->getValue();

					$do->addAttachment(
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

		return $do;

	}

	/**
	 * Convert event object to vevent object
	 *
	 * @since Release 1.0.0
	 *
	 * @param EventObject $so
	 *
	 * @return VCalendar
	 */
	public function fromEventObject(EventObject $so): VCalendar {
		// construct target object
		$do = new VCalendar();
		// base instance
		/** @var VEvent $vComponent */
		$vComponent = $do->add('VEVENT');
		// universal id
		if ($vComponent->UID) {
			$vComponent->UID->setValue($so->UUID);
		} else {
			$vComponent->add('UID', $so->UUID);
		}
		// product id
		if ($vComponent->PRODID) {
			$vComponent->PRODID->setValue($this->ProductionId);
		} else {
			$vComponent->add('PRODID', $this->ProductionId);
		}
		// common properties
		$this->fromEventInstanceObject($so, $vComponent);
		// mutated instances
		foreach ($so->OccurrenceMutations as $id => $mutation) {
			// Exclusion Mutations
			if ($mutation->mutationExclusion === true) {
				/** @var VEvent $vComponent */
				$vComponent = $do->add('VEVENT');
				$vComponent->add('EXDATE', $mutation->mutationId);
				if ($mutation->mutationTz) {
					$vComponent->{'EXDATE'}->add('TZID', $mutation->mutationTz);
				}
				continue;
			}

			// Standard Mutations
			/** @var VEvent $vComponent */
			$vComponent = $do->add('VEVENT');
			// universal id
			if ($vComponent->UID) {
				$vComponent->UID->setValue($so->UUID);
			} else {
				$vComponent->add('UID', $so->UUID);
			}
			// recurrence id
			if ($mutation->mutationTz !== null) {
				$mutationTz = $mutation->mutationTz;
			} elseif ($mutation->mutationId->getTimezone()->getName() !== 'UTC') {
				$mutationTz = $mutation->mutationId->getTimezone()->getName();
			} elseif ($so->StartsTZ) {
				$mutationTz = $so->StartsTZ->getName();
			} else {
				$mutationTz = null;
			}
			if ($mutationTz) {
				$mutationId = $mutation->mutationId->format($this->DateFormatDateTime);
			} else {
				$mutationId = $mutation->mutationId->format($this->DateFormatUTC);
			}
			if ($vComponent->{'RECURRENCE-ID'}) {
				$vComponent->{'RECURRENCE-ID'}->setValue($mutationId);
			} else {
				$vComponent->add('RECURRENCE-ID', $mutationId);
			}
			if ($mutationTz) {
				$vComponent->{'RECURRENCE-ID'}->add('TZID', $mutationTz);
			}
			// common properties
			$this->fromEventInstanceObject($mutation, $vComponent, $so);
		}

		return $do;
	}

	public function fromEventInstanceObject(EventObject|EventMutationObject $so, VEvent $do, ?EventObject $bo = null): VEvent {
		// creation date
		if ($so->CreatedOn) {
			$do->add('CREATED', $so->CreatedOn->format($this->DateFormatUTC));
			if ($do->DTSTAMP) {
				$do->DTSTAMP->setValue($so->CreatedOn->format($this->DateFormatUTC));
			} else {
				$do->add('DTSTAMP', $so->CreatedOn->format($this->DateFormatUTC));
			}
		}
		// modification date
		if ($so->ModifiedOn) {
			$do->add('LAST-MODIFIED', $so->ModifiedOn->format($this->DateFormatUTC));
		}
		// sequence
		if ($so->Sequence) {
			$do->add('SEQUENCE', $so->Sequence);
		} else {
			$do->add('SEQUENCE', 0);
		}
		// time zone
		if ($so->TimeZone) {
			$do->add('X-TIMEZONE', $so->TimeZone->getName());
		}
		// Starts Date, Time and Zone
		if ($so->StartsOn) {
			if ($so->Timeless) {
				$do->add('DTSTART', $so->StartsOn->format($this->DateFormatDateOnly));
			} else {
				$do->add('DTSTART', $so->StartsOn->format($this->DateFormatDateTime));
			}
			if ($so->StartsTZ) {
				$do->DTSTART->add('TZID', $so->StartsTZ->getName());
			} elseif ($so->TimeZone) {
				$do->DTSTART->add('TZID', $so->TimeZone->getName());
			} elseif ($bo && $bo->StartsTZ) {
				$do->DTSTART->add('TZID', $bo->StartsTZ->getName());
			}
		} elseif ($so->mutationId) {
			$do->add('DTSTART', $so->mutationId->format($this->DateFormatDateTime));
			if ($so->mutationTz) {
				$do->DTSTART->add('TZID', $so->mutationTz);
			} elseif ($bo && $bo->StartsTZ) {
				$do->DTSTART->add('TZID', $bo->StartsTZ->getName());
			}
		}
		// End Date, Time and Zone
		if ($so->EndsOn) {
			if ($so->Timeless) {
				$do->add('DTEND', $so->EndsOn->format($this->DateFormatDateOnly));
			} else {
				$do->add('DTEND', $so->EndsOn->format($this->DateFormatDateTime));
			}
			if ($so->EndsTZ) {
				$do->DTEND->add('TZID', $so->EndsTZ->getName());
			} elseif ($so->TimeZone) {
				$do->DTEND->add('TZID', $so->TimeZone->getName());
			}
		}
		// Duration
		if ($so->Duration) {
			//$do->add('DURATION',$so->Duration);
		}
		// Label
		if ($so->Label) {
			$do->add('SUMMARY', $so->Label);
		} elseif ($bo && $bo->Label) {
			$do->add('SUMMARY', $bo->Label);
		}
		// Notes
		if ($so->Description) {
			$do->add('DESCRIPTION', $so->Description);
		} elseif ($bo && $bo->Description) {
			$do->add('DESCRIPTION', $bo->Description);
		}
		// Physical Location(s)
		foreach ($so->LocationsPhysical as $entry) {
			/** @var Property $entity */
			$entity = $do->add('LOCATION', trim($entry->Name));
			$entity->add('X-ID', $entry->Id);
		}
		// Availability
		if ($so->Availability) {
			$do->add('TRANSP', match ($so->Availability) {
				EventAvailabilityTypes::Free => 'TRANSPARENT',
				default => 'OPAQUE',
			});
		}
		// Priority
		if ($so->Priority) {
			$do->add('PRIORITY', $so->Priority);
		}
		// Sensitivity
		if (isset($so->Sensitivity)) {
			$do->add('CLASS', match ($so->Sensitivity) {
				EventSensitivityTypes::Private => 'PRIVATE',
				EventSensitivityTypes::Secret => 'CONFIDENTIAL',
				default => 'PUBLIC',
			});
		}
		// Color
		if ($so->Color) {
			$do->add('COLOR', trim($so->Color));
		}
		// Tag(s)
		if (count($so->Tags) > 0) {
			$do->add('CATEGORIES', implode(', ', (array)$so->Tags));
		}
		// Participant(s)
		foreach ($so->Participants as $entry) {
			if (in_array(EventParticipantRoleTypes::Owner, iterator_to_array($entry->Roles), true)) {
				$entity = $do->add('ORGANIZER', 'mailto:' . $entry->Address);
			} else {
				$entity = $do->add('ATTENDEE', 'mailto:' . $entry->address);
			}
			/** @var Property $entity */
			$entity->add('X-ID', $entry->Id);
			// Participant Type
			$entity->add('CUTYPE', match ($entry->Type) {
				EventParticipantTypes::Individual => 'INDIVIDUAL',
				EventParticipantTypes::Group => 'GROUP',
				EventParticipantTypes::Resource => 'RESOURCE',
				EventParticipantTypes::Location => 'ROOM',
				default => 'UNKNOWN',
			});
			// Participant Status
			$entity->add('PARTSTAT', match ($entry->Type) {
				EventParticipantStatusTypes::Accepted => 'ACCEPTED',
				EventParticipantStatusTypes::Declined => 'DECLINED',
				EventParticipantStatusTypes::Tentative => 'TENTATIVE',
				EventParticipantStatusTypes::Delegated => 'DELEGATED',
				default => 'NEEDS-ACTION',
			});
			// Participant Name
			if ($entry->Name) {
				$entity->add('CN', $entry->Name);
			}
		}
		// Notifications
		foreach ($so->Notifications as $entry) {
			$entity = $do->add('VALARM');
			/** @var Property $entity */
			$entity->add('X-ID', $entry->Id);
			// Notifications Type
			$entity->add('ACTION', match ($entry->Type) {
				EventNotificationTypes::Email => 'EMAIL',
				default => 'DISPLAY',
			});
			// Notifications Pattern
			switch ($entry->Pattern) {
				case EventNotificationPatterns::Absolute:
					$entity->add('VALUE', $entry->When, []);
					break;
				case EventNotificationPatterns::Relative:
					$entity->add(
						'TRIGGER',
						$this->toDurationPeriod($entry->Offset),
						['RELATED' => ($entry->Anchor === EventNotificationAnchorTypes::End) ? 'END' : 'START']
					);
					break;
			}
		}
		// Occurrence
		foreach ($so->OccurrencePatterns as $entry) {
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

			$do->add('RRULE', $pattern);
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
					$do->add('ATTACH', "/f/" . $entry->Id, $p);
					unset($p);
				}
				// Referance
				elseif ($entry->Store == 'R' && !empty($entry->Data)) {
					$p = array();
					$p['FMTTYPE'] = $entry->Type;
					$do->add('ATTACH', $entry->Data, $p);
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
						$do->add(
							'ATTACH',
							'X-FILENAME="' . $entry->Name . '":' . $entry->Data,
							$p
						);
					}
					else {
						$do->add(
							'ATTACH',
							'X-FILENAME="' . $entry->Name . '":' .  base64_encode($entry->Data),
							$p
						);
					}
				}

			}
		}
		*/

		return $do;
	}

	public function generateSignature(EventObject $to): string {

		// clone self
		$o = clone $to;
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
			($period->y > 0) => $period->format('%rP%yY%mM%dDT%hH%iM'),
			($period->m > 0) => $period->format('%rP%mM%dDT%hH%iM'),
			($period->d > 0) => $period->format('%rP%dDT%hH%iM'),
			($period->h > 0) => $period->format('%rPT%hH%iM'),
			default => $period->format('%rPT%iM')
		};

	}

	private function convertToInt(array $values): array {

		foreach ($values as $key => $value) {
			$values[$key] = (int)$value;
		}

		return $values;

	}

}
