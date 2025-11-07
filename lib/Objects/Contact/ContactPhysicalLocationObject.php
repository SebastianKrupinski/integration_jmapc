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

namespace OCA\JMAPC\Objects\Contact;

class ContactPhysicalLocationObject {

	public string|null $Box = null;
	public string|null $Unit = null;
	public string|null $Street = null;
	public string|null $Locality = null;
	public string|null $Region = null;
	public string|null $Code = null;
	public string|null $Country = null;

	public string|null $Label = null;
	public string|null $Coordinates = null;
	public string|null $TimeZone = null;

	public string|null $Id = null;
	public int|null $Index = null;
	public int|null $Priority = null;
	public string|null $Context = null;
	public string|null $Language = null;
	public string|null $URI = null;

}
