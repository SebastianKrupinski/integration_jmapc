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
namespace OCA\JMAPC\Store;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method getId(): int
 * @method getUid(): ?string
 * @method setUid(string $uid): void
 * @method getLabel(): ?string
 * @method setLabel(string $label): void
 * @method getLocationProtocol(): string
 * @method setLocationProtocol(string $value): void
 * @method getLocationHost(): string
 * @method setLocationHost(string $value): void
 * @method getLocationPort(): int
 * @method setLocationPort(int $value): void
 * @method getLocationPath(): ?string
 * @method setLocationPath(string $value): void
 * @method getLocationSecurity(): bool
 * @method setLocationSecurity(bool $value): void
 * @method getAuth(): ?string
 * @method setAuth(string $value): void
 * @method getBauthId(): ?string
 * @method setBauthId(string $value): void
 * @method getBauthSecret(): ?string
 * @method setBauthSecret(string $value): void
 * @method getOauthId(): ?string
 * @method setOauthId(string $value): void
 * @method getOauthAccessToken(): ?string
 * @method setOauthAccessToken(string $value): void
 * @method getOauthAccessLocation(): ?string
 * @method setOauthAccessLocation(string $value): void
 * @method getOauthAccessExpiry(): ?int
 * @method setOauthAccessExpiry(int $value): void
 * @method getOauthRefreshToken(): ?string
 * @method setOauthRefreshToken(string $value): void
 * @method getOauthRefreshLocation(): ?string
 * @method setOauthRefreshLocation(string $value): void
 * @method getAddressPrimary(): ?string
 * @method setAddressPrimary(string $value): void
 * @method getAddressAlternate(): ?string
 * @method setAddressAlternate(string $value): void
 * @method getEnabled(): bool
 * @method setEnabled(bool $value): void
 * @method getConnected(): bool
 * @method setConnected(bool $value): void
 * @method getDebug(): bool
 * @method setDebug(bool $value): void
 * @method getHarmonizationState(): ?int
 * @method setHarmonizationState(int $value): void
 * @method getHarmonizationStart(): ?int
 * @method setHarmonizationStart(int $value): void
 * @method getHarmonizationEnd(): ?int
 * @method setHarmonizationEnd(int $value): void
 * @method getMailMode(): string
 * @method setMailMode(?string $value): void
 * @method getContactsMode(): ?string
 * @method setContactsMode(?string $value): void
 * @method getCalendarsMode(): ?string
 * @method setCalendarsMode(string $value): void
 * @method getTasksMode(): ?string
 * @method setTasksMode(string $value): void
 */
class ServiceEntity extends Entity implements JsonSerializable {
	protected ?string $uid = null;
	protected ?string $label = null;
	protected string $locationProtocol = 'https://';
	protected string $locationHost = 'localhost';
	protected int $locationPort = 443;
	protected ?string $locationPath = null;
	protected int $locationSecurity = 1;
	protected ?string $auth = null;
	protected ?string $bauthId = null;
	protected ?string $bauthSecret = null;
	protected ?string $oauthId = null;
	protected ?string $oauthAccessToken = null;
	protected ?string $oauthAccessLocation = null;
	protected ?int $oauthAccessExpiry = null;
	protected ?string $oauthRefreshToken = null;
	protected ?string $oauthRefreshLocation = null;
	protected ?string $addressPrimary = null;
	protected ?string $addressAlternate = null;
	protected bool $connected = false;
	protected bool $enabled = true;
	protected bool $debug = false;
	protected ?int $harmonizationState = 0;
	protected ?int $harmonizationStart = 0;
	protected ?int $harmonizationEnd = 0;
	protected ?string $mailMode = null;
	protected ?string $contactsMode = null;
	protected ?string $calendarsMode = null;
	protected ?string $tasksMode = null;
	
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uid' => $this->uid,
			'label' => $this->label,
		];
	}
}
