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

	public ?string $InstanceId = null;
	public DateTime|DateTimeImmutable|null $CreatedOn = null;
	public DateTime|DateTimeImmutable|null $ModifiedOn = null;
	public ?int $Sequence = null;
	public ?DateTimeZone $TimeZone = null;
	public DateTime|DateTimeImmutable|null $StartsOn = null;
	public ?DateTimeZone $StartsTZ = null;
	public DateTime|DateTimeImmutable|null $EndsOn = null;
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
