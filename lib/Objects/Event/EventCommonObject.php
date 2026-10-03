<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
