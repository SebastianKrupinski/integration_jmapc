<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Contact;

use OCA\JMAPC\Objects\BaseCollection;

class ContactPhysicalLocationCollection extends BaseCollection {
	public function __construct($data = []) {
		parent::__construct(ContactPhysicalLocationObject::class, $data);
	}
}
