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
namespace OCA\JMAPC\AppInfo;

use OCA\JMAPC\Events\UserDeletedListener;
use OCA\JMAPC\Notification\Notifier;
use OCA\JMAPC\Providers\Mail\Provider as MailProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\Notification\IManager as INotificationManager;

use OCP\User\Events\UserDeletedEvent;

/**
 * Class Application
 *
 * @package OCA\JMAPC\AppInfo
 */
class Application extends App implements IBootstrap {
	// assign application identification
	public const APP_ID = 'integration_jmapc';
	public const APP_TAG = 'JMAPC';
	public const APP_LABEL = 'JMAP Client';

	public function __construct(array $urlParams = []) {
		if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
			throw new \Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		
		// register notifications
		$manager = $this->getContainer()->get(INotificationManager::class);
		$manager->registerNotifierService(Notifier::class);

		// register event handlers
		$dispatcher = $this->getContainer()->get(IEventDispatcher::class);
		$dispatcher->addServiceListener(UserDeletedEvent::class, UserDeletedListener::class);

		if (method_exists($context, 'registerMailProvider')) {
			$context->registerMailProvider(MailProvider::class);
		}

		/*
		try {
			if (class_exists('\OCA\ContactsService\ContactsManager', true)) {
				\OCA\ContactsService\ContactsManager::registerProvider(
					'jmapc',
					\OCA\JMAPC\Providers\Contacts\Provider::class
				);
			}
		} catch (\Exception $e) {
			// Handle the exception if needed
		}
		*/
	}

	public function boot(IBootContext $context): void {

	}

}
