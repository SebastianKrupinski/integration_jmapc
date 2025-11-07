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
use DateTime;
use DateTimeImmutable;
use DateTimeZone;

class EventCommonObject {

	public string|null $InstanceId = null;
	public DateTime|DateTimeImmutable|null $CreatedOn = null;
	public DateTime|DateTimeImmutable|null $ModifiedOn = null;
	public int|null $Sequence = null;
	public DateTimeZone|null $TimeZone = null;
	public DateTime|DateTimeImmutable|null $StartsOn = null;
	public DateTimeZone|null $StartsTZ = null;
	public DateTime|DateTimeImmutable|null $EndsOn = null;
	public DateTimeZone|null $EndsTZ = null;
	public DateInterval|null $Duration = null;
	public bool|null $Timeless = false;
	public string|null $Label = null;
	public string|null $Description = null;
	public EventLocationPhysicalCollection $LocationsPhysical;
	public EventLocationVirtualCollection $LocationsVirtual;
	public EventAvailabilityTypes|null $Availability = null;
	public int|null $Priority = null;
	public EventSensitivityTypes|null $Sensitivity = null;
	public string|null $Color = null;
	public EventTagCollection $Categories;
	public EventTagCollection $Tags;
	public EventOrganizerObject $Organizer;
	public EventParticipantCollection $Participants;
	public EventNotificationCollection $Notifications;
	public EventAttachmentCollection $Attachments;
	
	public function __construct() {
		$this->Attachments = new EventAttachmentCollection();
		$this->Participants = new EventParticipantCollection();
		$this->LocationsPhysical = new EventLocationPhysicalCollection();
		$this->LocationsVirtual = new EventLocationVirtualCollection();
		$this->Notifications = new EventNotificationCollection();
		$this->Organizer = new EventOrganizerObject();
		$this->Tags = new EventTagCollection();
	}

}
