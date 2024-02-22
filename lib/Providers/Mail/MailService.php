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
namespace OCA\JMAPC\Providers\Mail;

use OCP\Mail\Provider\IService;
use OCP\Mail\Provider\ICollection;
use OCP\Mail\Provider\ICollections;
use OCP\Mail\Provider\ICollectionCreate;
use OCP\Mail\Provider\ICollectionDelete;
use OCP\Mail\Provider\ICollectionFetch;
use OCP\Mail\Provider\ICollectionMove;
use OCP\Mail\Provider\ICollectionSearch;
use OCP\Mail\Provider\ICollectionUpdate;
use OCP\Mail\Provider\IEntity;
use OCP\Mail\Provider\IEntityCopy;
use OCP\Mail\Provider\IEntityCreate;
use OCP\Mail\Provider\IEntityDelete;
use OCP\Mail\Provider\IEntityFetch;
use OCP\Mail\Provider\IEntityMove;
use OCP\Mail\Provider\IEntitySearch;
use OCP\Mail\Provider\IEntitySend;
use OCP\Mail\Provider\IEntityUpdate;
use OCP\Mail\Provider\IServiceIdentity;
use OCP\Mail\Provider\IServiceLocation;

use OCA\JMAPC\AppInfo\Application;


class MailService implements IService, ICollections,
	ICollectionCreate, ICollectionDelete, ICollectionFetch,
	ICollectionMove, ICollectionSearch, ICollectionUpdate,
	IEntityCopy, IEntityCreate, IEntityDelete, IEntityFetch,
	IEntityMove, IEntitySearch, IEntitySend, IEntityUpdate {

	private string $_id;
	private string $_label;
	private MailServiceIdentityBAuth|MailServiceIdentityOAuth $_identity;
	private MailServiceLocation $_location;

	public function __construct(
		string $id,
		string $label,
		MailServiceIdentityBAuth|MailServiceIdentityOAuth $identity,
		MailServiceLocation $location
	) {
		
		$this->_id = $id;
		$this->_label = $label;
		$this->_identity = $identity;
		$this->_location = $location;

	}

	/**
	 * An arbitrary unique text string identifying this service
	 * @since 1.0.0
	 */
	public function id(): string {

		return $this->_id;

	}

	/**
	 * The localized human frendly name of this provider
	 * @since 1.0.0
	 */
	public function getLabel(): string {

		return $this->_label;

	}

	/**
	 * The localized human frendly name of this provider
	 * @since 1.0.0
	 */
	public function setLabel(string $value) {

		$this->_label = $value;

	}

	/**
	 * The localized human frendly name of this provider
	 * @since 1.0.0
	 */
	public function getIdentity(): IServiceIdentity {

		return $this->_identity;

	}

	/**
	 * The localized human frendly name of this provider
	 * @since 1.0.0
	 */
	public function setIdentity(IServiceIdentity $value) {

		$this->_identity = $value;

	}

	/**
	 * The localized human frendly name of this provider
	 * @since 1.0.0
	 */
	public function getLocation(): IServiceLocation {

		return $this->_location;

	}

	/**
	 * The localized human frendly name of this provider
	 * @since 1.0.0
	 */
	public function setLocation(IServiceLocation $value) {

		$this->_location = $value;

	}

	public function collections(string $cid, string $scope, array $options = []): array {

		// retrieve list of collections (folders) code

	}

	public function collectionCreate(string $fcid, string $label, array $option = []): string {

		// create a collection code

	}

	public function collectionDelete(string $fcid, string $cid, array $option = []): bool {

		// delete a collection code

	}

	public function collectionFetch(string $fcid, string $cid, array $options = []): ICollection {

		// retrieve a collection code

	}

	public function collectionMove(string $fcid, string $scid, string $dcid, array $options = []): string {

		// move a collection code

	}

	public function collectionSearch(string $cid, string $filter, string $scope, array $options = []): array {

		// search for a collection code

	}

	public function collectionUpdate(string $fcid, string $cid, string $label, array $option = []): string {

		// update a collection code

	}

	public function entityCopy(string $scid, string $seid, string $dcid, array $options = []): string {

		// copy a message code

	}

	public function entityCreate(string $cid, string $content, array $option = []): string {

		// create a message code

	}

	public function entityDelete(string $cid, string $eid, array $options = []): bool {

		// delete a message code

	}

	public function entityFetch(string $cid, string $eid, array $options = []): IEntity {

		// retrieve a message code

	}

	public function entityMove(string $scid, string $seid, string $dcid, array $options = []): string {

		// move a message code

	}

	public function entitySearch(string $cid, array $filter, string $scope, array $options = []): array {

		//search for a message code

	}

	public function entitySend(array $to, array $cc = [], $bcc = [], string $content, array $option = []): string {

		/// send a message code

	}

	public function entityUpdate(string $cid, string $eid, string $content, array $option = []): string {

		// update a message code

	}

}
