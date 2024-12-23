<?php
//declare(strict_types=1);

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

use Datetime;
use finfo;
use Psr\Log\LoggerInterface;

use JmapClient\Client;
use JmapClient\Responses\ResponseException;
use JmapClient\Requests\Contacts\AddressBookGet;
use JmapClient\Requests\Contacts\AddressBookSet;
use JmapClient\Requests\Contacts\AddressBookQuery;
use JmapClient\Requests\Contacts\ContactGet;
use JmapClient\Requests\Contacts\ContactSet;
use JmapClient\Requests\Contacts\ContactQuery;
use JmapClient\Requests\Contacts\ContactSubmissionSet;
use JmapClient\Requests\Contacts\ContactParameters;

use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\ContactCollectionObject;
use OCA\JMAPC\Objects\ContactObject;
use OCA\JMAPC\Objects\ContactAttachmentObject;

class RemoteContactsService {

	protected Client $dataStore;
    protected string $dataAccount;

    protected ?string $resourceNamespace = null;
    protected ?string $resourceCollectionLabel = null;
    protected ?string $resourceEntityLabel = null;

    protected array $defaultCollectionProperties = [];
	protected array $defaultEntityProperties = [];

	public function __construct () {

	}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
        // evaluate if client is connected 
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
        // determine capabilities
        if ($this->dataStore->sessionCapable('https://www.fastmail.com/dev/contacts', false)) {
            $this->resourceNamespace = 'https://www.fastmail.com/dev/contacts';
            $this->resourceCollectionLabel = null;
            $this->resourceEntityLabel = 'Contact';
        }
        // determine account
        if ($dataAccount === null) {
            if ($this->resourceNamespace !== null) {
                $this->dataAccount = $dataStore->sessionAccountDefault($this->resourceNamespace, false);
            } else {
                $this->dataAccount = $dataStore->sessionAccountDefault('contacts');
            }
        }
        else {
            $this->dataAccount = $dataAccount;
        }

	}

	/**
     * retrieve properties for specific collection
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionFetch(string $location, string $id): ICollection {
		// construct get request
		$r0 = new AddressBookGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return new Collection($response->object(0)->parametersRaw());
    }

	/**
     * create collection in remote storage
     * 
     * @since Release 1.0.0
	 * 
	 */
	public function collectionCreate(string $location, string $label): string {
		// construct set request
		$r0 = new AddressBookSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$m0 = $r0->create('1');
		$m0->in($location);
		$m0->label($label);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->created()['1']['id'];
    }

    /**
     * update collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionUpdate(string $location, string $id, string $label): string {
        // construct set request
		$r0 = new AddressBookSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->label($label);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

    /**
     * delete collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionDelete(string $location, string $id): string {
        // construct set request
		$r0 = new AddressBookSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$r0->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->deleted()[0];
    }

	/**
     * move collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionMove(string $sourceLocation, string $id, string $destinationLocation): string {
        // construct set request
		$r0 = new AddressBookSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->in($destinationLocation);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

	/**
     * list of collections in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function collectionList(?string $location = null, ?string $scope = null): array {
		// construct get request
 		$r0 = new AddressBookGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set target to query request
        if ($location !== null) {
            $r0->target($location);
        }
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
        // determine if command errored
        if (method_exists($response, 'type')) {
            if ($response->type() === 'unknownMethod') {
                throw new JmapUnknownMethod($response->description(), 1);
            } else {
                throw new Exception($response->type() . ': ' . $response->description(), 1);
            }
        }

        // convert json objects to collection objects
        $list = [];
		foreach ($response->objects() as $ro) {
            $collection = new ContactCollectionObject(
                $ro->id(),
                $ro->name(),
                $ro->priority(),
                $ro->visible(),
                $ro->color(),
            );
			$list[] = $collection;
		}
		// return collection of collections
		return $list;
	}

	/**
     * search for collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function collectionSearch(string $location, string $filter, string $scope): array {
        // construct set request
		$r0 = new AddressBookQuery($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set location constraint
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// set name constraint
		if (!empty($filter)) {
			$r0->filter()->Name($filter);
		}
		// construct get request
		$r1 = new AddressBookGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceCollectionLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to collection objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Collection($message->parametersRaw());
		}
		// return collection of collections
		return $list;
    }

	/**
     * retrieve entity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityFetch(string $location, string $id, string $particulars = 'D'): IMessage {
		// construct set request
		$r0 = new ContactGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->target($id);
		// select properties to return
		//$r0->property(...$this->defaultMailProperties);
		$r0->bodyAll(true);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return new Message($response->object(0)->parametersRaw());
    }
    
	/**
     * create entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityCreate(string $location, IMessage $message): string {
		// construct set request
		$r0 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->create('1')->parametersRaw($message->getParameters())->in($location);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->created()['1']['id'];
    }

    /**
     * update entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityUpdate(string $location, string $id, IMessage $message): string {
		//
		//TODO: Replace this code with an actual property update instead of replacement
		//
		// construct set request
		//$r0 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		//$r0->create('1')->parametersRaw($message->getParameters())->in($location);
		// construct set request
		//$r1 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		//$r1->delete($id);
		// construct set request
		$r0 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$messageData = $message->getParameters();
		$messageData['id'] = $id;
		$r0->update($id)->parametersRaw($messageData);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }
    
    /**
     * delete entity from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityDelete(string $location, string $id): string {
        // construct set request
		$r0 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->delete($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return (string) $response->deleted()[0];
    }

	/**
     * copy entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityCopy(string $sourceLocation, string $id, string $destinationLocation): string {
        
    }

	/**
     * move entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityMove(string $sourceLocation, string $id, string $destinationLocation): string {
        // construct set request
		$r0 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$m0 = $r0->update($id);
		$m0->in($destinationLocation);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($id, $response->updated()) ? (string) $id : '';
    }

	/**
     * retrieve entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityList(string $location, IRange $range = null, string $sort = null, string $particulars = 'D'): array {
		// construct query request
		$r0 = new ContactQuery($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set location constraint
        if (!empty($location)) {
            $r0->filter()->in($location);
        }
		// set range constraint
		if ($range !== null) {
			if ($range->type()->value === 'absolute') {
				$r0->startAbsolute($range->getStart())->limitAbsolute($range->getCount());
			}
			if ($range->type()->value === 'relative') {
				$r0->startRelative($range->getStart())->limitRelative($range->getCount());
			}
		}
		// set sort
		if ($sort !== null) {
			match($sort) {
				'received' => $r0->sort()->received(),
				'sent' => $r0->sort()->sent(),
				'from' => $r0->sort()->from(),
				'to' => $r0->sort()->to(),
				'subject' => $r0->sort()->subject(),
				'size' => $r0->sort()->size(),
			};
		}

        if ($particulars === 'B') {
            // transmit request and receive response
            $bundle = $this->dataStore->perform([$r0]);
            // extract response
            $response = $bundle->response(0);
            // return response
            return $response[1];
        } else {
            // construct get request
            $r1 = new ContactGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
            // set target to query request
            $r1->targetFromRequest($r0, '/ids');
            // select properties to return
            $r1->property(...$this->defaultMailProperties);
            // transmit request and receive response
            $bundle = $this->dataStore->perform([$r0, $r1]);
            // extract response
            $response = $bundle->response(1);
            // convert json objects to message objects
            $list = $response->objects();
            foreach ($list as $id => $message) {
                $list[$id] = new Message($message->parametersRaw());
            }
            // return message collection
            return $list;
        }
		
    }

	/**
     * search for entities from remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entitySearch(string $location, array $filter = null, IRange $range = null, string $sort = null, string $particulars = 'D'): array {
		// construct query request
		$r0 = new ContactQuery($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set location constraint
		$r0->filter()->in($location);
		// set filter constraints
		if (!empty($filter)) {
			// extract request filter
			$rf = $r0->filter();
			// iterate filter values
			foreach ($filter as $key => $value) {
				if (method_exists($rf, $key)) {
					$rf->$key($value);
				}
			}
		}
		// set range constraint
		if ($range !== null) {
			if ($range->type()->value === 'absolute') {
				$r0->startAbsolute($range->getStart())->limitAbsolute($range->getCount());
			}
			if ($range->type()->value === 'relative') {
				$r0->startRelative($range->getStart())->limitRelative($range->getCount());
			}
		}
		// set sort
		if ($sort !== null) {
			match($sort) {
				'received' => $r0->sort()->received(),
				'sent' => $r0->sort()->sent(),
				'from' => $r0->sort()->from(),
				'to' => $r0->sort()->to(),
				'subject' => $r0->sort()->subject(),
				'size' => $r0->sort()->size(),
			};
		}
		// construct get request
		$r1 = new ContactGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// select properties to return
		$r1->property(...$this->defaultMailProperties);
		$r1->bodyAll(true);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// convert json objects to message objects
		$list = $response->objects();
		foreach ($list as $id => $message) {
			$list[$id] = new Message($message->parametersRaw());
		}
		// return message collection
		return $list;
    }

    /**
     * retrieve collection entity attachment from remote storage
     * 
     * @since Release 1.0.0
     * 
     * @param array $batch		Batch of Attachment ID's
	 * 
	 * @return array
	 */
	public function fetchAttachment(array $batch): array {

		// check to for entries in batch collection
        if (count($batch) == 0) {
            return array();
        }
		// retrieve attachments
		$rs = $this->RemoteCommonService->fetchAttachment($this->DataStore, $batch);
		// construct response collection place holder
		$rc = array();
		// check for response
		if (isset($rs)) {
			// process collection of objects
			foreach($rs as $entry) {
				if (!isset($entry->ContentType) || $entry->ContentType == 'application/octet-stream') {
					$type = \OCA\JMAPC\Utile\MIME::fromFileName($entry->Name);
				} else {
					$type = $entry->ContentType;
				}
				// insert attachment object in response collection
				$rc[] = new EventAttachmentObject(
					'D',
					$entry->AttachmentId->Id, 
					$entry->Name,
					$type,
					'B',
					$entry->Size,
					$entry->Content
				);
			}
		}
		// return response collection
		return $rc;

    }

    /**
     * create collection item attachment in remote storage
     * 
     * @since Release 1.0.0
     * 
	 * @param string $aid - Affiliation ID
     * @param array $sc - Collection of ContactAttachmentObject(S)
	 * 
	 * @return array
	 */
	public function entityCreateAttachment(string $aid, array $batch): array {

		// check to for entries in batch collection
        if (count($batch) == 0) {
            return array();
        }
		// construct command collection place holder
		$cc = array();
		// process batch
		foreach ($batch as $key => $entry) {
			// construct command object
			$co = new \OCA\JMAPC\Utile\Eas\Type\FileAttachmentType();
			$co->IsInline = false;
			$co->ContentId = $entry->Name;
			$co->ContentType = $entry->Type;
            $co->Name = $entry->Name;
			$co->Size = $entry->Size;

            if ($entry->Flag == 'CP') {
                $co->IsContactPhoto = true;
            }
            else {
                $co->IsContactPhoto = false;
            }
            
			switch ($entry->Encoding) {
				case 'B':
					$co->Content = $entry->Data;
					break;
				case 'B64':
					$co->Content = base64_decode($entry->Data);
					break;
			}
			// insert command object in to collection
			$cc[] = $co;
		}
		// execute command(s)
		$rs = $this->RemoteCommonService->createAttachment($this->DataStore, $aid, $cc);
		// construct results collection place holder
		$rc = array();
		// check for response
		if (isset($rs)) {
			// process collection of objects
			foreach($rs as $key => $entry) {
				$ro = $batch[$key];
				$ro->Id = $entry->AttachmentId->Id;
				$ro->Data = null;
				$ro->AffiliateId = $entry->AttachmentId->RootItemId;
				$ro->AffiliateState = $entry->AttachmentId->RootItemChangeKey;
				$rc[] = $ro;
			}

        }
		// return response collection
		return $rc;
    }

    /**
     * delete collection item attachment from remote storage
     * 
     * @since Release 1.0.0
     * 
     * @param string $aid - Attachment ID
	 * 
	 * @return array
	 */
	public function entityDeleteAttachment(array $batch): array {

		// check to for entries in batch collection
        if (count($batch) == 0) {
            return array();
        }
		// execute command
		$data = $this->RemoteCommonService->deleteAttachment($this->DataStore, $batch);

		return $data;

    }

    /**
     * convert remote EasObject to local ContactObject
     * 
     * @since Release 1.0.0
     * 
	 * @param EasObject $so     entity as EasObject
	 * 
	 * @return ContactObject    entity as ContactObject
	 */
	public function toContactObject(EasObject $so): ContactObject {

		// create object
		$co = new ContactObject();
        // Origin
		$co->Origin = 'R';
        // Label
        if (!empty($so->FileAs)) {
            $co->Label = $so->FileAs->getContents();
        }
		// Name - Last
        if (isset($so->LastName)) {
            $co->Name->Last = $so->LastName->getContents();
        }
        // Name - First
        if (isset($so->FirstName)) {
            $co->Name->First = $so->FirstName->getContents();
        }
        // Name - Other
        if (isset($so->MiddleName)) {
            $co->Name->Other = $so->MiddleName->getContents();
        }
        // Name - Prefix
        if (isset($so->Title)) {
            $co->Name->Prefix = $so->Title->getContents();
        }
        // Name - Suffix
        if (isset($so->Suffix)) {
            $co->Name->Suffix = $so->Suffix->getContents();
        }
        // Name - Phonetic - Last
        if (isset($so->YomiLastName)) {
            $co->Name->PhoneticLast = $so->YomiLastName->getContents();
        }
        // Name - Phonetic - First
        if (isset($so->YomiFirstName)) {
            $co->Name->PhoneticFirst = $so->YomiFirstName->getContents();
        }
        // Name - Aliases
        if (isset($so->NickName)) {
            $co->Name->Aliases = $so->NickName->getContents();
        }
        // Birth Day
        if (!empty($so->Birthday)) {
            $co->BirthDay =  new DateTime($so->Birthday->getContents());
        }
        // Photo
        if (isset($so->Picture)) {
            $co->Photo->Type = '';
            $co->Photo->Data = (is_array($so->Picture)) ? $so->Picture[0]->getContents() : $so->Picture->getContents();
        }
        // Partner
        if (!empty($so->Spouse)) {
            $co->Partner = $so->Spouse->getContents();
        }
        // Anniversary Day
        if (!empty($so->Anniversary)) {
            $co->NuptialDay =  new DateTime($so->Anniversary->getContents());
        }
        // Address(es)
        // Work
        if (isset($so->BusinessAddressStreet) ||
            isset($so->BusinessAddressCity) ||
            isset($so->BusinessAddressState) ||
            isset($so->BusinessAddressPostalCode) ||
            isset($so->BusinessAddressCountry)
        ) {
            $address = new \OCA\JMAPC\Objects\ContactAddressObject();
            $address->Type = 'WORK';
            // Street
            if (isset($so->BusinessAddressStreet)) {
                $address->Street = $so->BusinessAddressStreet->getContents();
            }
            // Locality
            if (isset($so->BusinessAddressCity)) {
                $address->Locality = $so->BusinessAddressCity->getContents();
            }
            // Region
            if (isset($so->BusinessAddressState)) {
                $address->Region = $so->BusinessAddressState->getContents();
            }
            // Code
            if (isset($so->BusinessAddressPostalCode)) {
                $address->Code = $so->BusinessAddressPostalCode->getContents();
            }
            // Country
            if (isset($so->BusinessAddressCountry)) {
                $address->Country = $so->BusinessAddressCountry->getContents();
            }
            // add address to collection
            $co->Address[] = $address;
        }
        // Home
        if (isset($so->HomeAddressStreet) ||
            isset($so->HomeAddressCity) ||
            isset($so->HomeAddressState) ||
            isset($so->HomeAddressPostalCode) ||
            isset($so->HomeAddressCountry)
        ) {
            $address = new \OCA\JMAPC\Objects\ContactAddressObject();
            $address->Type = 'HOME';
            // Street
            if (isset($so->HomeAddressStreet)) {
                $address->Street = $so->HomeAddressStreet->getContents();
            }
            // Locality
            if (isset($so->HomeAddressCity)) {
                $address->Locality = $so->HomeAddressCity->getContents();
            }
            // Region
            if (isset($so->HomeAddressState)) {
                $address->Region = $so->HomeAddressState->getContents();
            }
            // Code
            if (isset($so->HomeAddressPostalCode)) {
                $address->Code = $so->HomeAddressPostalCode->getContents();
            }
            // Country
            if (isset($so->HomeAddressCountry)) {
                $address->Country = $so->HomeAddressCountry->getContents();
            }
            // add address to collection
            $co->Address[] = $address;
        }
        // Other
        if (isset($so->OtherAddressStreet) ||
            isset($so->OtherAddressCity) ||
            isset($so->OtherAddressState) ||
            isset($so->OtherAddressPostalCode) ||
            isset($so->OtherAddressCountry)
        ) {
            $address = new \OCA\JMAPC\Objects\ContactAddressObject();
            $address->Type = 'OTHER';
            // Street
            if (isset($so->OtherAddressStreet)) {
                $address->Street = $so->OtherAddressStreet->getContents();
            }
            // Locality
            if (isset($so->OtherAddressCity)) {
                $address->Locality = $so->OtherAddressCity->getContents();
            }
            // Region
            if (isset($so->OtherAddressState)) {
                $address->Region = $so->OtherAddressState->getContents();
            }
            // Code
            if (isset($so->OtherAddressPostalCode)) {
                $address->Code = $so->OtherAddressPostalCode->getContents();
            }
            // Country
            if (isset($so->OtherAddressCountry)) {
                $address->Country = $so->OtherAddressCountry->getContents();
            }
            // add address to collection
            $co->Address[] = $address;
        }
        // Phone - Business Phone 1
        if (!empty($so->BusinessPhoneNumber)) {
            $co->addPhone('WORK', 'VOICE', $so->BusinessPhoneNumber->getContents());
        }
        // Phone - Business Phone 2
        if (!empty($so->Business2PhoneNumber)) {
            $co->addPhone('WORK', 'VOICE', $so->Business2PhoneNumber->getContents());
        }
        // Phone - Business Fax
        if (!empty($so->BusinessFaxNumber)) {
            $co->addPhone('WORK', 'FAX', $so->BusinessFaxNumber->getContents());
        }
        // Phone - Home Phone 1
        if (!empty($so->HomePhoneNumber)) {
            $co->addPhone('HOME', 'VOICE', $so->HomePhoneNumber->getContents());
        }
        // Phone - Home Phone 2
        if (!empty($so->Home2PhoneNumber)) {
            $co->addPhone('HOME', 'VOICE', $so->Home2PhoneNumber->getContents());
        }
        // Phone - Home Fax
        if (!empty($so->HomeFaxNumber)) {
            $co->addPhone('HOME', 'FAX', $so->HomeFaXNumber->getContents());
        }
        // Phone - Mobile
        if (!empty($so->MobilePhoneNumber)) {
            $co->addPhone('CELL', null, $so->MobilePhoneNumber->getContents());
        }
        // Email(s)
        if (!empty($so->Email1Address)) {
            $co->addEmail('WORK', $this->sanitizeEmail($so->Email1Address->getContents()));
        }
        if (!empty($so->Email2Address)) {
            $co->addEmail('HOME', $this->sanitizeEmail($so->Email2Address->getContents()));
        }
        if (!empty($so->Email3Address)) {
            $co->addEmail('OTHER', $this->sanitizeEmail($so->Email3Address->getContents()));
        }
        // IMPP(s)
        if (!empty($so->IMAddress)) {
            $co->addIMPP('WORK', $so->IMAddress->getContents());
        }
        if (!empty($so->IMAddress2)) {
            $co->addIMPP('HOME', $so->IMAddress2->getContents());
        }
        if (!empty($so->IMAddress3)) {
            $co->addIMPP('OTHER', $so->IMAddress3->getContents());
        }
        // Manager Name
        if (!empty($so->ManagerName)) {
            $co->Name->Manager =  $so->ManagerName->getContents();
        }
        // Assistant Name
        if (!empty($so->AssistantName)) {
            $co->Name->Assistant =  $so->AssistantName->getContents();
        }
        // Occupation Organization
        if (!empty($so->CompanyName)) {
            $co->Occupation->Organization = $so->CompanyName->getContents();
        }
        // Occupation Department
        if (!empty($so->Department)) {
            $co->Occupation->Department = $so->Department->getContents();
        }
        // Occupation Title
        if (!empty($so->JobTitle)) {
            $co->Occupation->Title = $so->JobTitle->getContents();
        }
        // Occupation Role
        if (!empty($so->Profession)) {
            $co->Occupation->Role = $so->Profession->getContents();
        }
        // Occupation Location
        if (!empty($so->OfficeLocation)) {
            $co->Occupation->Location = $so->OfficeLocation->getContents();
        }
        // Tag(s)
        if (isset($so->Categories)) {
            if (!is_array($so->Categories->Category)) {
                $so->Categories->Category = [$so->Categories->Category];
            }
			foreach($so->Categories->Category as $entry) {
				$co->addTag($entry->getContents());
			}
        }
        // Notes
        if (!empty($so->Body->Data)) {
            $co->Notes = $so->Body->Data->getContents();
        }
        // URL / Website
        if (isset($so->WebPage)) {
            $co->URL = $so->WebPage->getContents();
        }
        // Attachment(s)
		if (isset($so->Attachments)) {
			if (!is_array($so->Attachments->Attachment)) {
				$so->Attachments->Attachment = [$so->Attachments->Attachment];
			}
			foreach($so->Attachments->Attachment as $entry) {
				$type = \OCA\JMAPC\Utile\MIME::fromFileName($entry->DisplayName->getContents());
				$co->addAttachment(
					'D',
					$entry->FileReference->getContents(), 
					$entry->DisplayName->getContents(),
					$type,
					'B',
					$entry->EstimatedDataSize->getContents()
				);
			}
		}

		return $co;

    }

    /**
     * convert remote ContactObject to local EasObject
     * 
     * @since Release 1.0.0
     * 
	 * @param ContactObject $so     entity as ContactObject
	 * 
	 * @return EasObject            entity as EasObject
	 */
	public function fromContactObject(ContactObject $so): EasObject {

		// create object
		$eo = new EasObject('AirSync');
        // Label
        if (!empty($so->Label)) {
            $eo->FileAs = new EasProperty('Contacts', $so->Label);
        }
		// Name - Last
        if (!empty($so->Name->Last)) {
            $eo->LastName = new EasProperty('Contacts', $so->Name->Last);
        }
        // Name - First
        if (!empty($so->Name->First)) {
            $eo->FirstName = new EasProperty('Contacts', $so->Name->First);
        }
        // Name - Other
        if (!empty($so->Name->Other)) {
            $eo->MiddleName = new EasProperty('Contacts', $so->Name->Other);
        }
        // Name - Prefix
        if (!empty($so->Name->Prefix)) {
            $eo->Title = new EasProperty('Contacts', $so->Name->Prefix);
        }
        // Name - Suffix
        if (!empty($so->Name->Suffix)) {
            $eo->Suffix = new EasProperty('Contacts', $so->Name->Suffix);
        }
        // Name - Phonetic - Last
        if (!empty($so->Name->PhoneticLast)) {
            $eo->YomiLastName = new EasProperty('Contacts', $so->Name->PhoneticLast);
        }
        // Name - Phonetic - First
        if (!empty($so->Name->PhoneticFirst)) {
            $eo->YomiFirstName = new EasProperty('Contacts', $so->Name->PhoneticFirst);
        }
        // Name - Aliases
        if (!empty($so->Name->Aliases)) {
            $eo->NickName = new EasProperty('Contacts', $so->Name->Aliases);
        }
        // Birth Day
        if (!empty($so->BirthDay)) {
            $eo->Birthday = new EasProperty('Contacts', $so->BirthDay->format('Y-m-d\\T11:59:00.000\\Z')); //2018-01-01T11:59:00.000Z
        }
        // Partner
        if (!empty($so->Partner)) {
            $eo->Spouse = new EasProperty('Contacts', $so->Partner);
        }
        // Anniversary Day
        if (!empty($so->NuptialDay)) {
            $eo->Anniversary = new EasProperty('Contacts', $so->NuptialDay->format('Y-m-d\\T11:59:00.000\\Z')); //2018-01-01T11:59:00.000Z
        }
        // Address(es)
        if (count($so->Address) > 0) {
            $types = [
                'WORK' => true,
                'HOME' => true,
                'OTHER' => true
            ];
            foreach ($so->Address as $entry) {
                // Address - Work
                if ($entry->Type == 'WORK' && $types[$entry->Type]) {
                    // Street
                    if (!empty($entry->Street)) {
                        $eo->BusinessAddressStreet = new EasProperty('Contacts', $entry->Street);
                    }
                    // Locality
                    if (!empty($entry->Locality)) {
                        $eo->BusinessAddressCity = new EasProperty('Contacts', $entry->Locality);
                    }
                    // Region
                    if (!empty($entry->Region)) {
                        $eo->BusinessAddressState = new EasProperty('Contacts', $entry->Region);
                    }
                    // Code
                    if (!empty($entry->Code)) {
                        $eo->BusinessAddressPostalCode = new EasProperty('Contacts', $entry->Code);
                    }
                    // Country
                    if (!empty($entry->Country)) {
                        $eo->BusinessAddressCountry = new EasProperty('Contacts', $entry->Country);
                    }
                    // disable type
                    $types[$entry->Type] = false;
                }
                // Address - Home
                if ($entry->Type == 'HOME' && $types[$entry->Type]) {
                    // Street
                    if (!empty($entry->Street)) {
                        $eo->HomeAddressStreet = new EasProperty('Contacts', $entry->Street);
                    }
                    // Locality
                    if (!empty($entry->Locality)) {
                        $eo->HomeAddressCity = new EasProperty('Contacts', $entry->Locality);
                    }
                    // Region
                    if (!empty($entry->Region)) {
                        $eo->HomeAddressState = new EasProperty('Contacts', $entry->Region);
                    }
                    // Code
                    if (!empty($entry->Code)) {
                        $eo->HomeAddressPostalCode = new EasProperty('Contacts', $entry->Code);
                    }
                    // Country
                    if (!empty($entry->Country)) {
                        $eo->HomeAddressCountry = new EasProperty('Contacts', $entry->Country);
                    }
                    // disable type
                    $types[$entry->Type] = false;
                }
                // Address - Other
                if ($entry->Type == 'OTHER' && $types[$entry->Type]) {
                    // Street
                    if (!empty($entry->Street)) {
                        $eo->OtherAddressStreet = new EasProperty('Contacts', $entry->Street);
                    }
                    // Locality
                    if (!empty($entry->Locality)) {
                        $eo->OtherAddressCity = new EasProperty('Contacts', $entry->Locality);
                    }
                    // Region
                    if (!empty($entry->Region)) {
                        $eo->OtherAddressState = new EasProperty('Contacts', $entry->Region);
                    }
                    // Code
                    if (!empty($entry->Code)) {
                        $eo->OtherAddressPostalCode = new EasProperty('Contacts', $entry->Code);
                    }
                    // Country
                    if (!empty($entry->Country)) {
                        $eo->OtherAddressCountry = new EasProperty('Contacts', $entry->Country);
                    }
                    // disable type
                    $types[$entry->Type] = false;
                }
            }
        }
        // Phone(s)
        if (count($so->Phone) > 0) {
            $types = array(
                'WorkVoice1' => true,
                'WorkVoice2' => true,
                'WorkFax' => true,
                'HomeVoice1' => true,
                'HomeVoice2' => true,
                'HomeFax' => true,
                'Cell' => true,
            );
            foreach ($so->Phone as $entry) {
                if (empty($entry->Number)) {
                    continue;
                }
                if ($entry->Type == 'WORK' && $entry->SubType == 'VOICE' && $types['WorkVoice1']) {
                    $eo->BusinessPhoneNumber = new EasProperty('Contacts', $entry->Number);
                    $types['WorkVoice1'] = false;
                }
                elseif ($entry->Type == 'WORK' && $entry->SubType == 'VOICE' && $types['WorkVoice2']) {
                    $eo->Business2PhoneNumber = new EasProperty('Contacts', $entry->Number);
                    $types['WorkVoice2'] = false;
                }
                elseif ($entry->Type == 'WORK' && $entry->SubType == 'FAX' && $types['WorkFax']) {
                    $eo->BusinessFaxNumber = new EasProperty('Contacts', $entry->Number);
                    $types['WorkFax'] = false;
                }
                elseif ($entry->Type == 'HOME' && $entry->SubType == 'VOICE' && $types['HomeVoice1']) {
                    $eo->HomePhoneNumber = new EasProperty('Contacts', $entry->Number);
                    $types['HomeVoice1'] = false;
                }
                elseif ($entry->Type == 'HOME' && $entry->SubType == 'VOICE' && $types['HomeVoice2']) {
                    $eo->Home2PhoneNumber = new EasProperty('Contacts', $entry->Number);
                    $types['HomeVoice2'] = false;
                }
                elseif ($entry->Type == 'HOME' && $entry->SubType == 'FAX' && $types['HomeFax']) {
                    $eo->HomeFaxNumber = new EasProperty('Contacts', $entry->Number);
                    $types['HomeFax'] = false;
                }
                elseif ($entry->Type == 'CELL' && $types['Cell']) {
                    $eo->MobilePhoneNumber = new EasProperty('Contacts', $entry->Number);
                    $types['Cell'] = false;
                }
            }
        }
        // Email(s)
        if (count($so->Email) > 0) {
            $types = array(
                'WORK' => true,
                'HOME' => true,
                'OTHER' => true
            );
            foreach ($so->Email as $entry) {
                if (isset($types[$entry->Type]) && $types[$entry->Type] == true && !empty($entry->Address)) {
                    switch ($entry->Type) {
                        case 'WORK':
                            $eo->Email1Address = new EasProperty('Contacts', $entry->Address);
                            break;
                        case 'HOME':
                            $eo->Email2Address = new EasProperty('Contacts', $entry->Address);
                            break;
                        case 'OTHER':
                            $eo->Email3Address = new EasProperty('Contacts', $entry->Address);
                            break;
                    }
                    $types[$entry->Type] = false;
                }
            }
        }
        // Manager Name
        if (!empty($so->Name->Manager)) {
            $eo->ManagerName = new EasProperty('Contacts', $so->Name->Manager);
        }
        // Assistant Name
        if (!empty($so->Name->Assistant)) {
            $eo->AssistantName = new EasProperty('Contacts', $so->Name->Assistant);
        }
        // Occupation Organization
        if (!empty($so->Occupation->Organization)) {
            $eo->CompanyName = new EasProperty('Contacts', $so->Occupation->Organization);
        }
        // Occupation Department
        if (!empty($so->Occupation->Department)) {
            $eo->Department = new EasProperty('Contacts', $so->Occupation->Department);
        }
        // Occupation Title
        if (!empty($so->Occupation->Title)) {
            $eo->JobTitle = new EasProperty('Contacts', $so->Occupation->Title);
        }
        // Occupation Location
        if (!empty($so->Occupation->Location)) {
            $eo->OfficeLocation = new EasProperty('Contacts', $so->Occupation->Location);
        }
        // Tag(s)
        if (count($so->Tags) > 0) {
            $eo->Categories = new EasObject('Contacts');
            $eo->Categories->Category = new EasCollection('Contacts');
            foreach($so->Tags as $entry) {
                $eo->Categories->Category[] = new EasProperty('Contacts', $entry);
            }
        }
        // Notes
        if (!empty($so->Notes)) {
            $eo->Body = new EasObject('AirSyncBase');
            $eo->Body->Type = new EasProperty('AirSyncBase', EasTypes::BODY_TYPE_TEXT);
            //$eo->Body->EstimatedDataSize = new EasProperty('AirSyncBase', strlen($so->Notes));
            $eo->Body->Data = new EasProperty('AirSyncBase', $so->Notes);
        }
        // URL / Website
        if (!empty($so->URI)) {
            $eo->WebPage = new EasProperty('Contacts', $so->URI);
        }
        
		return $eo;

    }

    public function generateSignature(ContactObject $eo): string {
        
        // clone self
        $o = clone $eo;
        // remove non needed values
        unset($o->ID, $o->UUID, $o->RCID, $o->REID, $o->Origin, $o->Signature, $o->CreatedOn, $o->ModifiedOn);
        // generate signature
        return md5(json_encode($o));

    }

    public function sanitizeEmail(string $address): string|null {

        // evaluate if address is empty
        if (!(strlen(trim($address)) > 0)) {
            return null;
        }
        // evaluate if address is already a valid mail address
        if (\OCA\JMAPC\Utile\Validator::email($address)) {
            return $address;
        }
        // evaluate if address is in AddressBook format
        if (str_contains($address, '<') && str_contains($address, '>')) {
            preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $address, $matches);
            if (count($matches[0]) > 0) {
                return $matches[0][0];
            }
        }
        // if all fails return null
        return null;
        
    }

}
