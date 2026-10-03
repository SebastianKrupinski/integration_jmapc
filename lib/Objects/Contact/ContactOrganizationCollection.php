<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

use OCA\JMAPC\Objects\BaseCollection;
use OCA\JMAPC\Objects\Common\CollectionIndexTrait;
use OCA\JMAPC\Objects\Common\CollectionPriorityTrait;

class ContactOrganizationCollection extends BaseCollection {

	use CollectionPriorityTrait, CollectionIndexTrait;

	public function __construct($data = []) {
		parent::__construct(ContactOrganizationObject::class, $data);
	}
}
