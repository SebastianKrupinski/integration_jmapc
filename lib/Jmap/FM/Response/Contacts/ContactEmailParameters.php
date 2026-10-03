<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Jmap\FM\Response\Contacts;

use JmapClient\Responses\ResponseParameters;

class ContactEmailParameters extends ResponseParameters {

	public function type(): ?string {
		return $this->parameter('type') ?? 'personal';
	}

	public function value(): ?string {
		return $this->parameter('value');
	}

	public function label(): ?string {
		return $this->parameter('label');
	}

	public function default(): bool {
		return $this->parameter('isDefault');
	}

}
