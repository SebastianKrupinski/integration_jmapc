<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Objects\Task;

class TaskAttachmentObject {

	public string $Store; // D - Data Store / R - Reference / E - Enclosed
	public ?string $Id;
	public ?string $Name;
	public ?string $Type;
	public ?string $Encoding; // B - Binary / B64 - Base64
	public ?string $Size;
	public ?string $Data;

	public function __construct(
		?string $store = null,
		?string $id = null,
		?string $name = null,
		?string $type = null,
		?string $encoding = null,
		?string $size = null,
		?string $data = null,
	) {
		$this->Store = $store;
		$this->Id = $id;
		$this->Name = $name;
		$this->Type = $type;
		$this->Encoding = $encoding;
		$this->Size = $size;
		$this->Data = $data;
	}
}
