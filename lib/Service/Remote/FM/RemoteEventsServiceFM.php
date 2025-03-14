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

use Exception;
use JmapClient\Requests\Calendar\EventChanges;
use JmapClient\Requests\Calendar\EventGet;
use JmapClient\Responses\ResponseException;
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\BaseStringCollection;
use OCA\JMAPC\Objects\DeltaObject;
use OCA\JMAPC\Service\Remote\RemoteEventsService;

class RemoteEventsServiceFM extends RemoteEventsService{

	    /**
     * delta of changes for specific collection in remote storage
     * 
     * @since Release 1.0.0
     * 
	 */
    public function entityDeltaSpecific(?string $location, string $state, string $granularity = 'D'): DeltaObject {
        // construct set request
		$r0 = new EventChanges($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		// set state constraint
		if (!empty($state)) {
			$r0->state($state);
		} else {
			$r0->state('0');
		}
		// construct get for created
		$r1 = new EventGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		$r1->targetFromRequest($r0, '/created');
        $r1->property('calendarIds', 'id', 'created', 'updated');
		// construct get for updated
		$r2 = new EventGet($this->dataAccount, '', $this->resourceNamespace, $this->resourceEntityLabel);
		$r2->targetFromRequest($r0, '/updated');
        $r2->property('calendarIds', 'id', 'created', 'updated');
		// transceive
		$bundle = $this->dataStore->perform([$r0, $r1, $r2]);
		// extract response
		$response0 = $bundle->response(0);
		$response1 = $bundle->response(1);
		$response2 = $bundle->response(2);
		$response3 = $bundle->response(3);
		// determine if command errored
        if ($response0 instanceof ResponseException) {
            if ($response0->type() === 'unknownMethod') {
                throw new JmapUnknownMethod($response0->description(), 1);
            } else {
                throw new Exception($response0->type() . ': ' . $response0->description(), 1);
            }
        }
		// convert jmap object to delta object
        $delta = new DeltaObject();
        $delta->signature = $response0->stateNew();
        $delta->additions = new BaseStringCollection();
		foreach ($response1->objects() as $entry) {
			if (in_array($location, $entry->in())) {
				$delta->additions[] = $entry->id();
			}
		}
        $delta->modifications = new BaseStringCollection();
		foreach ($response2->objects() as $entry) {
			if (in_array($location, $entry->in())) {
				$delta->modifications[] = $entry->id();
			}
		}
        $delta->deletions = new BaseStringCollection();
		foreach ($response0->deleted() as $entry) {
			$delta->deletions[] = $entry;
		}

        return $delta;
    }

}
