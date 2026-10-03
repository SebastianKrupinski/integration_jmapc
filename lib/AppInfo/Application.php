<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\AppInfo;

use OCA\JMAPC\Events\UserDeletedListener;
use OCA\JMAPC\Notification\Notifier;
use OCA\JMAPC\Providers\Files\MountProvider;
use OCA\JMAPC\Providers\Mail\Provider as MailProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IMountProviderCollection;
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
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IMountProviderCollection $mountProviderCollection, MountProvider $mountProvider): void {
			$mountProviderCollection->registerProvider($mountProvider);
		});
	}

}
