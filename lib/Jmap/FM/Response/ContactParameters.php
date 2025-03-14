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
namespace OCA\JMAPC\Jmap\FM\Response;

use DateTimeImmutable;
use JmapClient\Responses\ResponseParameters;

class ContactParameters extends ResponseParameters {
    
    public function __construct(array $response = []) {
        parent::__construct($response);
    }

    /* Metadata Properties */

    public function in(): array|null {
        // return value of parameter
        $value = $this->parameter('addressbookId');
        if ($value !== null) {
            return [$value];
        }
        return null;
    }
    
    public function id(): string|null {
        return $this->parameter('id');
    }

    public function uid(): string|null {
        return $this->parameter('uid');
    }

    public function type(): string|null {
        return $this->parameter('kind') ?? 'individual';
    }

    public function nameLast(): string|null {
        return $this->parameter('lastName');
    }

    public function nameFirst(): string|null {
        return $this->parameter('firstName');
    }

    public function namePrefix(): string|null {
        return $this->parameter('prefix');
    }

    public function nameSuffix(): string|null {
        return $this->parameter('suffix');
    }

    public function organizationName(): string|null {
        return $this->parameter('company');
    }

    public function organizationUnit(): string|null {
        return $this->parameter('department');
    }

    public function title(): string|null {
        return $this->parameter('jobTitle');
    }

    public function notes(): string|null {
        return $this->parameter('notes');
    }

    public function priority(): int|null {
        return (int)$this->parameter('importance');
    }

    public function birthDay(): string|null {
        return $this->parameter('birthday');
    }

    public function nuptialDay(): string|null {
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
