<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Jmap\FM\Response\Contacts;

use JmapClient\Responses\ResponseParameters;

class ContactLocationParameters extends ResponseParameters {

	public function type(): ?string {
		return $this->parameter('type') ?? 'home';
	}

	public function label(): ?string {
		return $this->parameter('label');
	}

	public function street(): ?string {
		return $this->parameter('street');
	}

	public function locality(): ?string {
		return $this->parameter('locality');
	}

	public function region(): ?string {
		return $this->parameter('region');
	}

	public function code(): ?string {
		return $this->parameter('postcode');
	}

	public function country(): ?string {
		return $this->parameter('country');
	}

}
