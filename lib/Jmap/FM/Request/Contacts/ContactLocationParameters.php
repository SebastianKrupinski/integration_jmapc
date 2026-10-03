<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Jmap\FM\Request\Contacts;

use JmapClient\Requests\RequestParameters;

class ContactLocationParameters extends RequestParameters {

	public function __construct(&$parameters = null) {
		parent::__construct($parameters);
	}

	public function type(string $value): self {
		$this->parameter('type', $value);
		return $this;
	}

	public function label(string $value): self {
		$this->parameter('label', $value);
		return $this;
	}

	public function street(string $value): self {
		$this->parameter('street', $value);
		return $this;
	}

	public function locality(string $value): self {
		$this->parameter('locality', $value);
		return $this;
	}

	public function region(string $value): self {
		$this->parameter('region', $value);
		return $this;
	}

	public function code(string $value): self {
		$this->parameter('postcode', $value);
		return $this;
	}

	public function country(string $value): self {
		$this->parameter('country', $value);
		return $this;
	}

	public function default(bool $value): self {
		$this->parameter('isDefault', $value);
		return $this;
	}

}
