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

namespace OCA\JMAPC\Service\Remote\FM;

use DateTimeImmutable;
use DateTimeInterface;
use JmapClient\Client;
use OCA\JMAPC\Jmap\FM\Request\ContactParameters as ContactParametersRequest;
use OCA\JMAPC\Jmap\FM\Request\ContactSet;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryObject;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryTypes;
use OCA\JMAPC\Objects\Contact\ContactEmailObject;
use OCA\JMAPC\Objects\Contact\ContactNoteObject;
use OCA\JMAPC\Objects\Contact\ContactObject as ContactObject;
use OCA\JMAPC\Objects\Contact\ContactOrganizationObject;
use OCA\JMAPC\Objects\Contact\ContactPhoneObject;
use OCA\JMAPC\Objects\Contact\ContactPhysicalLocationObject;
use OCA\JMAPC\Objects\Contact\ContactTitleObject;
use OCA\JMAPC\Objects\Contact\ContactTitleTypes;
use OCA\JMAPC\Objects\Contact\ContactVirtualLocationObject;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Service\Remote\RemoteContactsService;

class RemoteContactsServiceFM extends RemoteContactsService {

    private const DATE_ANNIVERSARY = 'Y-m-d';

	public function __construct () {}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

        parent::initialize($dataStore, $dataAccount);

        $this->resourceNamespace = 'https://www.fastmail.com/dev/contacts';
        $this->resourceCollectionLabel = null;
        $this->resourceEntityLabel = 'Contact';
        $dataStore->configureClassTypes('parameters', 'Contact', 'OCA\JMAPC\Jmap\FM\Response\ContactParameters');

	}

    /**
     * create entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityCreate(string $location, ContactObject $so): ContactObject|null {
		// convert entity
		$entity = $this->fromContactObject($so);
		// construct set request
		$r0 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->create('1', $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return entity
		if (isset($response->created()['1']['id'])) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $response->created()['1']['id'];
			$ro->CreatedOn = isset($response->created()['1']['updated']) ? new DateTimeImmutable($response->created()['1']['updated']) : null;
			$ro->ModifiedOn = $ro->CreatedOn;
			$ro->Signature = $this->generateSignature($ro);
			return $ro;
		} else {
			return null;
		}
    }

    /**
     * update entity in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
	public function entityModify(string $location, string $id, ContactObject $so): ContactObject|null {
		// convert entity
		$entity = $this->fromContactObject($so);
		// construct set request
		$r0 = new ContactSet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($id, $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
        // convert jmap object to event object
		if (array_key_exists($id, $response->updated())) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $id;
			$ro->ModifiedOn = isset($response->updated()[$id]['updated']) ? new DateTimeImmutable($response->updated()[$id]['updated']) : null;
			$ro->Signature = $this->generateSignature($ro);
		} else {
			$ro = null;
		}
		// return entity information
		return $ro;
    }

    /**
     * convert jmap object to contact object
     * 
     * @since Release 1.0.0
     * 
	 */
	public function toContactObject($so): ContactObject {

		// create object
		$do = new ContactObject();
        // source origin
		$do->Origin = OriginTypes::External;
        // id
        if ($so->id()){
            $do->ID = $so->id();
        }
        // universal id
        if ($so->uid()){
            $do->UUID = $so->uid();
        }
        // name - last
        if ($so->nameLast()){
            $do->Name->Last = $so->nameLast();
        }
        // name - first
        if ($so->nameFirst()){
            $do->Name->First = $so->nameFirst();
        }
        // name - prefix
        if ($so->namePrefix()){
            $do->Name->Prefix = $so->namePrefix();
        }
        // name - suffix
        if ($so->nameSuffix()){
            $do->Name->Suffix = $so->nameSuffix();
        }
        // anniversary - birth day
        if ($so->birthday() && $so->birthday() !== '0000-00-00'){
            $when = new DateTimeImmutable($so->birthday());
            if ($when){
                $anniversary = new ContactAnniversaryObject();
                $anniversary->Type = ContactAnniversaryTypes::Birth;
                $anniversary->When = $when;
                $do->Anniversaries[] = $anniversary;
            }
        }
        // anniversary - nuptial day
        if ($so->nuptialDay() && $so->nuptialDay() !== '0000-00-00'){
            $when = new DateTimeImmutable($so->nuptialDay());
            if ($when){
                $anniversary = new ContactAnniversaryObject();
                $anniversary->Type = ContactAnniversaryTypes::Nuptial;
                $anniversary->When = $when;
                $do->Anniversaries[] = $anniversary;
            }
        }
        // physical location(s)
		foreach ($so->location() as $id => $entry) {
			$location = new ContactPhysicalLocationObject();
			$location->Context  = $entry->type();
			$location->Label = $entry->label();
            $location->Street = $entry->street();
            $location->Locality = $entry->locality();
            $location->Region  = $entry->region();
            $location->Code  = $entry->code();
            $location->Country  = $entry->country();
            $location->Id = (string)$id;
            $location->Index = $id;
			$do->PhysicalLocations[$id] = $location;
		}
        // phone(s)
		foreach ($so->phone() as $id => $entry) {
			$phone = new ContactPhoneObject();
			$phone->Context  = $entry->type();
			$phone->Number = $entry->value();
            $phone->Label = $entry->label();
            $phone->Id = (string)$id;
            $phone->Index = $id;
            if ($entry->default()) {
                $phone->Priority = 1;
            }
			$do->Phone[$id] = $phone;
		}
        // email(s)
		foreach ($so->email() as $id => $entry) {
			$email = new ContactEmailObject();
			$email->Context  = $entry->type();
			$email->Address = $entry->value();
            $email->Id = (string)$id;
            $email->Index = $id;
			$do->Email[$id] = $email;
		}
        // organization - name
        if ($so->organizationName()){
            $organization = new ContactOrganizationObject();
            $organization->Label = $so->organizationName();
            $organization->Id = '0';
            $organization->Index = 0;
            $organization->Priority = 1;
            $do->Organizations[] = $organization;
        }
        // title
        if ($so->title()){
            $title = new ContactTitleObject();
            $title->Kind = ContactTitleTypes::Title;
            $title->Label = $so->title();
            $title->Id = '0';
            $title->Index = 0;
            $title->Priority = 1;
            $do->Titles[] = $title;
        }
        // notes
        if ($so->notes()){
            $note = new ContactNoteObject();
            $note->Content = $so->notes();
            $note->Id = '0';
            $note->Index = 0;
            $note->Priority = 1;
            $do->Notes[] = $note;
        }
        // virtual locations
        if ($so->online()){
            foreach ($so->online() as $id => $entry) {
                $entity = new ContactVirtualLocationObject();
                $entity->Location = $entry->value();
                $entity->Context = $entry->type();
                $entity->Label = $entry->label();
                $email->Id = (string)$id;
                $email->Index = $id;
                $do->VirtualLocations[$id] = $entity;
            }
        }

		return $do;
        
    }

    /**
     * convert contact object to jmap object
     * 
     * @since Release 1.0.0
     * 
	 */
	public function fromContactObject(ContactObject $so): mixed {

        // create object
		$do = new ContactParametersRequest();
        // universal id
        if ($so->UUID){
            $do->uid($so->UUID);
        }
        // name - last
        if ($so->Name->Last){
            $do->nameLast($so->Name->Last);
        }
        // name - first
        if ($so->Name->First){
            $do->nameFirst($so->Name->First);
        }
        // name - prefix
        if ($so->Name->Prefix){
            $do->namePrefix($so->Name->Prefix);
        }
        // name - suffix
        if ($so->Name->Suffix){
            $do->nameSuffix($so->Name->Suffix);
        }
        // aliases
        // only one aliases is supported
        if ($so->Name->Aliases->count() > 0){
            $priority = $so->Name->Aliases->highestPriority();
            $do->organizationName($so->Name->Aliases[$priority]->Label);
        }
        // anniversaries
        $delta = [ContactAnniversaryTypes::Birth->name => true, ContactAnniversaryTypes::Nuptial->name => true];
        foreach ($so->Anniversaries as $id => $entry) {
            if ($entry->When === null) {
                continue;
            }
            if ($entry->Type === ContactAnniversaryTypes::Birth){
                $do->birthday($entry->When->format(self::DATE_ANNIVERSARY));
                unset($delta[ContactAnniversaryTypes::Birth->name]);
            }
            if ($entry->Type === ContactAnniversaryTypes::Nuptial){
                $do->nuptialDay($entry->When->format(self::DATE_ANNIVERSARY));
                unset($delta[ContactAnniversaryTypes::Nuptial->name]);
            }
        }
        foreach ($delta as $key => $value) {
            if ($key === ContactAnniversaryTypes::Birth->name){
                $do->birthday('0000-00-00');
            }
            if ($key === ContactAnniversaryTypes::Nuptial->name){
                $do->nuptialDay('0000-00-00');
            }
        }
        // phone(s)
        foreach ($so->Phone as $id => $entry) {
            $entity = $do->phone($id);
            $entity->value((string)$entry->Number);
            $context = strtolower($entry->Context);
            if (in_array($context, ['home', 'work', 'mobile', 'fax', 'page', 'other'], true)) {
                $entity->type($entry->Context);
            } else {
                $entity->type('other');
                $entity->label($entry->Context);
            }
            if ($entry->Priority === 1){
                $entity->default(true);
            }
        }
        // email(s)
        foreach ($so->Email as $id => $entry) {
            $entity = $do->email($id);
            $entity->value((string)$entry->Address);
            $context = strtolower($entry->Context);
            if (in_array($context, ['personal', 'work', 'other'], true)) {
                $entity->type($entry->Context);
            } else {
                $entity->type('other');
                $entity->label($entry->Context);
            }
            if ($entry->Priority === 1){
                $entity->default(true);
            }
        }
        // physical location(s)
        foreach ($so->PhysicalLocations as $id => $entry) {
            $entity = $do->location($id);
            $entity->type((string)$entry->Context);
            $entity->label((string)$entry->Label);
            $entity->street((string)$entry->Street);
            $entity->locality((string)$entry->Locality);
            $entity->region((string)$entry->Region);
            $entity->code((string)$entry->Code);
            $entity->country((string)$entry->Country);
            if ($entry->Priority === 1){
                $entity->default(true);
            }
        }
        // organization - name
        // only one organization is supported
        if ($so->Organizations->count() > 0){
            $priority = $so->Organizations->highestPriority();
            $do->organizationName($so->Organizations[$priority]->Label);
        }
        // titles
        // only one title is supported
        if ($so->Titles->count() > 0){
            $priority = $so->Titles->highestPriority(ContactTitleTypes::Title);
            if ($priority !== null){
                $do->title($so->Titles[$priority]->Label);
            }
        }
        // notes
        // only one note is supported
        if ($so->Notes->count() > 0){
            $do->notes($so->Notes[0]->Content);
        }
        // virtual locations
        foreach ($so->VirtualLocations as $id => $entry) {
            $entity = $do->online($id);
            $entity->type((string)$entry->Context);
            $entity->value((string)$entry->Location);
            $entity->label((string)$entry->Label);
        }

        return $do;

    }

}
