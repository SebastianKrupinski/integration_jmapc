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

namespace OCA\JMAPC\Objects\Event;

use OCA\JMAPC\Objects\OriginTypes;

class EventObject extends EventBaseObject {

	public OriginTypes|null $Origin = null;		// System
	public string|null $ID = null;              // System Entity Id
	public string|null $CID = null;             // System Collection Id
	public string|null $Signature = null;       // System Entity Signature
	public string|null $CCID = null;            // Correlation Collection Id
	public string|null $CEID = null;            // Correlation Entity Id
	public string|null $CESN = null;            // Correlation Signature

}
