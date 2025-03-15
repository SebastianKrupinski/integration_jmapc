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

namespace OCA\JMAPC\Objects\Event;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;
use OCA\JMAPC\Objects\OriginTypes;

class EventObject {

	public ?OriginTypes $Origin = null;        // System
	public ?string $ID = null;                      // System Entity Id
	public ?string $CID = null;                     // System Collection Id
	public ?string $Signature = null;               // System Entity Signature
	public ?string $CCID = null;                    // Correlation Collection Id
	public ?string $CEID = null;                    // Correlation Entity Id
	public ?string $CESN = null;                    // Correlation Signature
	public ?string $UUID = null;
	public ?DateTimeInterface $CreatedOn = null;
	public ?DateTimeInterface $ModifiedOn = null;
	public ?int $Sequence = null;
	public ?DateTimeZone $TimeZone = null;
	public ?DateTimeInterface $StartsOn = null;
	public ?DateTimeZone $StartsTZ = null;
	public ?DateTimeInterface $EndsOn = null;
	public ?DateTimeZone $EndsTZ = null;
	public ?DateInterval $Duration = null;
	public ?bool $Timeless = false;
	public ?string $Label = null;
	public ?string $Description = null;
	public EventLocationPhysicalCollection $LocationsPhysical;
	public EventLocationVirtualCollection $LocationsVirtual;
	public ?EventAvailabilityTypes $Availability = null;
	public ?int $Priority = null;
	public ?EventSensitivityTypes $Sensitivity = null;
	public ?string $Color = null;
	public EventTagCollection $Categories;
	public EventTagCollection $Tags;
	public EventOrganizerObject $Organizer;
	public EventParticipantCollection $Participants;
	public EventOccurrenceCollection $OccurrencePatterns;
	public EventOccurrenceCollection $OccurrenceExceptions;
	public array $OccurrenceMutations;
	public EventNotificationCollection $Notifications;
	public EventAttachmentCollection $Attachments;
	public ?array $Other = [];
	
	public function __construct() {
		$this->Attachments = new EventAttachmentCollection();
		$this->Participants = new EventParticipantCollection();
		$this->LocationsPhysical = new EventLocationPhysicalCollection();
		$this->LocationsVirtual = new EventLocationVirtualCollection();
		$this->Notifications = new EventNotificationCollection();
		$this->Organizer = new EventOrganizerObject();
		$this->OccurrencePatterns = new EventOccurrenceCollection();
		$this->OccurrenceExceptions = new EventOccurrenceCollection();
		$this->Tags = new EventTagCollection();
	}

}
