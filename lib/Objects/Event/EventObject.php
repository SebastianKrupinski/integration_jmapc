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

namespace OCA\JMAPC\Objects\Event;

use DateTime;
use DateTimeZone;
use DateInterval;
use DateTimeImmutable;
use OCA\JMAPC\Objects\OriginTypes;

class EventObject {

    public ?OriginTypes $Origin = null;        // System
    public ?string $ID = null;                      // System Entity Id
    public ?string $CID = null;                     // System Collection Id
    public ?string $Signature = null;               // System Entity Signature
    public ?string $CCID = null;                    // Correlation Collection Id
    public ?string $CEID = null;                    // Correlation Entity Id
    public ?string $CESN = null;                    // Correlation Signature
    public ?string $UUID = null;                    // Event UUID
    public DateTime|DateTimeImmutable|null $CreatedOn = null;    // Event Creation Date/Time
    public DateTime|DateTimeImmutable|null $ModifiedOn = null;   // Event Modification Date/Time
    public ?int $Sequence = null;                   // Event Sequence
    public ?DateTimeZone $TimeZone = null;          // Event Time Zone
    public DateTime|DateTimeImmutable|null $StartsOn = null;     // Event Start Date/Time
    public ?DateTimeZone $StartsTZ = null;          // Event Start Time Zone
    public DateTime|DateTimeImmutable|null $EndsOn = null;       // Event End Date/Time
    public ?DateTimeZone $EndsTZ = null;            // Event End Time Zone
    public ?DateInterval $Duration = null;          // Event Duration
    public ?bool $Timeless = false;                 // Event Without time
    public ?string $Label = null;                   // Event Title/Summary
    public ?string $Description = null;             // Event Description
    public EventLocationPhysicalCollection $LocationsPhysical;      // Event Location(s)
    public EventLocationVirtualCollection $LocationsVirtual;        // Event Location(s)
    public ?EventAvailabilityTypes $Availability = null;            // Event Free Busy Status
    public ?int $Priority = null;                   // Event Priority / 0 - Low / 1 - Normal / 2 - High
    public ?EventSensitivityTypes $Sensitivity = null;             // Event Sensitivity
    public ?string $Color = null;                   // Event Display Color
    public EventTagCollection $Categories;
    public EventTagCollection $Tags;
    public EventOrganizerObject $Organizer;         // Event Organizer
    public EventParticipantCollection $Participants;// Event Attendee(s)
    public EventOccurrenceCollection $OccurrencePatterns;
    public EventOccurrenceCollection $OccurrenceExceptions;
    public array $OccurrenceMutations;
    public EventNotificationCollection $Notifications; // Event Reminder(s)
    public EventAttachmentCollection $Attachments;  // Event Attachment(s)
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
