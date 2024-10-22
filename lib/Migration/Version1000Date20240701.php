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

namespace OCA\JMAPC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version1000Date20240701 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('jmapc_services')) {
			$table = $schema->createTable('jmapc_services');
			// id
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true
			]);
			// user id
			$table->addColumn('uid', Types::STRING, [
				'length' => 255,
				'notnull' => true
			]);
			// service label
			$table->addColumn('label', Types::STRING, [
				'length' => 255,
				'notnull' => false
			]);


			// service location protocol
			$table->addColumn('location_protocol', Types::STRING, [
				'length' => 8,
				'notnull' => true
			]);
			// service location host
			$table->addColumn('location_host', Types::STRING, [
				'length' => 255,
				'notnull' => true
			]);
			// service location port
			$table->addColumn('location_port', Types::INTEGER, [
				'notnull' => true,
				'default' => 443
			]);
			// service location path
			$table->addColumn('location_path', Types::STRING, [
				'length' => 255,
				'notnull' => false
			]);
			// service location security
			$table->addColumn('location_security', Types::INTEGER, [
				'notnull' => true,
				'default' => 1
			]);


			// service authentication
			$table->addColumn('auth', Types::STRING, [
				'length' => 8,
				'notnull' => true
			]);
			// service authentication basic id
			$table->addColumn('bauth_id', Types::STRING, [
				'length' => 255,
				'notnull' => false
			]);
			// service authentication basic secret
			$table->addColumn('bauth_secret', Types::STRING, [
				'length' => 255,
				'notnull' => false
			]);

			// service authentication bearer id
			$table->addColumn('oauth_id', Types::STRING, [
				'length' => 255,
				'notnull' => false
			]);
			// service authentication bearer access token
			$table->addColumn('oauth_access_token', Types::STRING, [
				'length' => 255,
				'notnull' => false
			]);
			// service authentication bearer access location
			$table->addColumn('oauth_access_location', new \Doctrine\DBAL\Types\TextType, [
				'notnull' => false
			]);
			// service authentication bearer access expiry
			$table->addColumn('oauth_access_expiry', Types::INTEGER, [
				'notnull' => false
			]);

			// service authentication bearer refresh token
			$table->addColumn('oauth_refresh_token', Types::STRING, [
				'length' => 255,
				'notnull' => false
			]);
			// service authentication bearer refresh location
			$table->addColumn('oauth_refresh_location', new \Doctrine\DBAL\Types\TextType, [
				'notnull' => false
			]);

			// service connected
			$table->addColumn('connected', Types::INTEGER, [
				'notnull' => false
			]);
			// service enabled
			$table->addColumn('enabled', Types::INTEGER, [
				'notnull' => false
			]);
			// contacts harmonization
			$table->addColumn('contacts_harmonize', Types::INTEGER, [
				'notnull' => false
			]);
			// contacts prevalence
			$table->addColumn('contacts_prevalence', Types::STRING, [
				'length' => 2,
				'notnull' => false
			]);	
			// events harmonization
			$table->addColumn('events_harmonize', Types::INTEGER, [
				'notnull' => false
			]);
			// events prevalence
			$table->addColumn('events_prevalence', Types::STRING, [
				'length' => 2,
				'notnull' => false
			]);	
			// tasks harmonization
			$table->addColumn('tasks_harmonize', Types::INTEGER, [
				'notnull' => false
			]);
			// tasks prevalence
			$table->addColumn('tasks_prevalence', Types::STRING, [
				'length' => 2,
				'notnull' => false
			]);			

			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}


}
