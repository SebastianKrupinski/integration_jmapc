<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Jmap\FM\Request\Contacts;

use JmapClient\Requests\RequestParameters;

class ContactEmailParameters extends RequestParameters {

	public function __construct(&$parameters = null) {
		parent::__construct($parameters);
	}

	public function type(string $value): self {
		$this->parameter('type', $value);
		return $this;
	}

	public function value(string $value): self {
		$this->parameter('value', $value);
		return $this;
	}

	public function label(string $value): self {
		$this->parameter('label', $value);
		return $this;
	}

	public function default(bool $value): self {
		$this->parameter('isDefault', $value);
		return $this;
	}

}
