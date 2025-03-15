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
namespace OCA\JMAPC\Store\Local;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method getId(): int
 * @method getUID(): string
 * @method setUID(string $uid): void
 * @method getSID(): string
 * @method setSID(int $sid): void
 * @method getCID(): string
 * @method setCID(int $sid): void
 * @method getUUID(): string
 * @method setUUID(string $uuid): void
 * @method getSignature(): string
 * @method setSignature(string $uuid): void
 * @method getRCID(): string
 * @method setRCID(string $rcid): void
 * @method getREID(): string
 * @method setREID(string $reid): void
 * @method getRESN(): string
 * @method setRESN(string $resn): void
 * @method getData(): string
 * @method setData(string $data): void
 * @method getLabel(): string
 * @method setLabel(string $label): void
 * @method getDescription(): string
 * @method setDescription(string $description): void
 * @method getStartsOn(): string
 * @method setStartsOn(int $startson): void
 * @method getEndsOn(): string
 * @method setEndsOn(int $endson): void
 */
class TaskEntity extends Entity implements JsonSerializable {
	protected ?string $uid = null;
	protected ?int $sid = null;
	protected ?int $cid = null;
	protected ?string $uuid = null;
	protected ?string $signature = null;
	protected ?string $rcid = null;
	protected ?string $reid = null;
	protected ?string $resn = null;
	protected ?string $data = null;
	protected ?string $label = null;
	protected ?string $description = null;
	protected ?int $startson = null;
	protected ?int $endson = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uid' => $this->uid,
			'sid' => $this->sid,
			'cid' => $this->cid,
			'uuid' => $this->uuid,
			'signature' => $this->signature,
			'rcid' => $this->rcid,
			'reid' => $this->reid,
			'resn' => $this->resn,
			'data' => $this->data,
			'label' => $this->label,
			'description' => $this->description,
			'startson' => $this->startson,
			'endson' => $this->endson,
		];
	}
}
