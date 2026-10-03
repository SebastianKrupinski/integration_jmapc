<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Jmap\FM\Response\Contacts;

use JmapClient\Responses\ResponseParameters;

class ContactParameters extends ResponseParameters {

	/* Metadata Properties */

	public function in(): ?array {
		// return value of parameter
		$value = $this->parameter('addressbookId');
		if ($value !== null) {
			return [$value];
		}
		return null;
	}

	public function id(): ?string {
		return $this->parameter('id');
	}

	public function uid(): ?string {
		return $this->parameter('uid');
	}

	public function type(): ?string {
		return $this->parameter('kind') ?? 'individual';
	}

	public function nameLast(): ?string {
		return $this->parameter('lastName');
	}

	public function nameFirst(): ?string {
		return $this->parameter('firstName');
	}

	public function namePrefix(): ?string {
		return $this->parameter('prefix');
	}

	public function nameSuffix(): ?string {
		return $this->parameter('suffix');
	}

	public function organizationName(): ?string {
		return $this->parameter('company');
	}

	public function organizationUnit(): ?string {
		return $this->parameter('department');
	}

	public function title(): ?string {
		return $this->parameter('jobTitle');
	}

	public function notes(): ?string {
		return $this->parameter('notes');
	}

	public function priority(): ?int {
		return (int)$this->parameter('importance');
	}

	public function birthDay(): ?string {
		return $this->parameter('birthday');
	}

	public function nuptialDay(): ?string {
		return $this->parameter('anniversary');
	}

	public function email(): array {
		$collection = $this->parameter('emails') ?? [];
		foreach ($collection as $key => $data) {
			$collection[$key] = new ContactEmailParameters($data);
		}
		return $collection;
	}

	public function phone(): array {
		$collection = $this->parameter('phones') ?? [];
		foreach ($collection as $key => $data) {
			$collection[$key] = new ContactPhoneParameters($data);
		}
		return $collection;
	}

	public function location(): array {
		$collection = $this->parameter('addresses') ?? [];
		foreach ($collection as $key => $data) {
			$collection[$key] = new ContactLocationParameters($data);
		}
		return $collection;
	}

	public function online(): array {
		$collection = $this->parameter('online') ?? [];
		foreach ($collection as $key => $data) {
			$collection[$key] = new ContactOnlineParameters($data);
		}
		return $collection;
	}

}
