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

namespace OCA\JMAPC\Service\Remote;

use JmapClient\Client;
use JmapClient\Requests\Blob\BlobGet;

class RemoteCommonService {

	protected Client $dataStore;
	protected string $dataAccount;

	protected ?string $resourceNamespace = null;
	protected ?string $resourceCollectionLabel = null;
	protected ?string $resourceEntityLabel = null;

	public function __construct() {
	}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
		// evaluate if client is connected
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
		// determine account
		if ($dataAccount === null) {
			if ($this->resourceNamespace !== null) {
				$this->dataAccount = $dataStore->sessionAccountDefault($this->resourceNamespace, false);
			} else {
				$this->dataAccount = $dataStore->sessionAccountDefault('blob');
			}
		} else {
			$this->dataAccount = $dataAccount;
		}

	}

	/**
	 * retrieve blob from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function blobFetch(string $account, string $id): ContactObject {
		// construct request
		$r0 = new BlobGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target($id);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to event object
		$eo = $this->toContactObject($response->object(0));
		$eo->Signature = $this->generateSignature($eo);

		return $eo;
	}

}
