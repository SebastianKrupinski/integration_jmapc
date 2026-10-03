<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Notification;

use InvalidArgumentException;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var IFactory */
	protected $factory;

	/** @var IUserManager */
	protected $userManager;

	/** @var INotificationManager */
	protected $notificationManager;

	/** @var IURLGenerator */
	protected $url;

	/**
	 * @param IFactory $factory
	 * @param IUserManager $userManager
	 * @param INotificationManager $notificationManager
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IFactory $factory,
		IUserManager $userManager,
		INotificationManager $notificationManager,
		IURLGenerator $urlGenerator) {
		$this->factory = $factory;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->url = $urlGenerator;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'integration_jmapc';
	}
	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get('integration_jmapc')->t('JMAP Connector');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'integration_jmapc') {
			// Not my app => throw
			throw new InvalidArgumentException();
		}

		$l = $this->factory->get('integration_jmapc', $languageCode);

		switch ($notification->getSubject()) {
			case 'contacts_harmonized':
				$p = $notification->getSubjectParameters();
				$content = 'The following changes where performed ';
				if ($p['LocalCreated'] > 0 || $p['RemoteCreated'] > 0) {
					$content .= "\n Created: ";
					if ($p['LocalCreated'] > 0) {
						$content .= $p['LocalCreated'] . ' - Local ';
					}
					if ($p['RemoteCreated'] > 0) {
						$content .= $p['RemoteCreated'] . ' - Remote ';
					}
				}
				if ($p['LocalUpdated'] > 0 || $p['RemoteUpdated'] > 0) {
					$content .= "\n Updated: ";
					if ($p['LocalUpdated'] > 0) {
						$content .= $p['LocalUpdated'] . ' - Local ';
					}
					if ($p['RemoteUpdated'] > 0) {
						$content .= $p['RemoteUpdated'] . ' - Remote ';
					}
				}
				if ($p['LocalDeleted'] > 0 || $p['RemoteDeleted'] > 0) {
					$content .= "\n Deleted: ";
					if ($p['LocalDeleted'] > 0) {
						$content .= $p['LocalDeleted'] . ' - Local ';
					}
					if ($p['RemoteDeleted'] > 0) {
						$content .= $p['RemoteDeleted'] . ' - Remote ';
					}
				}

				$notification->setParsedSubject("Contacts Syncronized \n");
				$notification->setRichMessage($content);

				return $notification;
			case 'events_harmonized':
				$p = $notification->getSubjectParameters();
				$content = "The following changes where performed \n";
				if ($p['LocalCreated'] > 0 || $p['RemoteCreated'] > 0) {
					$content .= "\n Created: ";
					if ($p['LocalCreated'] > 0) {
						$content .= $p['LocalCreated'] . ' - Local ';
					}
					if ($p['RemoteCreated'] > 0) {
						$content .= $p['RemoteCreated'] . ' - Remote ';
					}
				}
				if ($p['LocalUpdated'] > 0 || $p['RemoteUpdated'] > 0) {
					$content .= "\n Updated: ";
					if ($p['LocalUpdated'] > 0) {
						$content .= $p['LocalUpdated'] . ' - Local ';
					}
					if ($p['RemoteUpdated'] > 0) {
						$content .= $p['RemoteUpdated'] . ' - Remote ';
					}
				}
				if ($p['LocalDeleted'] > 0 || $p['RemoteDeleted'] > 0) {
					$content .= "\n Deleted: ";
					if ($p['LocalDeleted'] > 0) {
						$content .= $p['LocalDeleted'] . ' - Local ';
					}
					if ($p['RemoteDeleted'] > 0) {
						$content .= $p['RemoteDeleted'] . ' - Remote ';
					}
				}

				$notification->setParsedSubject("Events Syncronized \n");
				$notification->setRichMessage($content);

				return $notification;
			case 'tasks_harmonized':
				$p = $notification->getSubjectParameters();
				$content = "The following changes where performed \n";
				if ($p['LocalCreated'] > 0 || $p['RemoteCreated'] > 0) {
					$content .= "\n Created: ";
					if ($p['LocalCreated'] > 0) {
						$content .= $p['LocalCreated'] . ' - Local ';
					}
					if ($p['RemoteCreated'] > 0) {
						$content .= $p['RemoteCreated'] . ' - Remote ';
					}
				}
				if ($p['LocalUpdated'] > 0 || $p['RemoteUpdated'] > 0) {
					$content .= "\n Updated: ";
					if ($p['LocalUpdated'] > 0) {
						$content .= $p['LocalUpdated'] . ' - Local ';
					}
					if ($p['RemoteUpdated'] > 0) {
						$content .= $p['RemoteUpdated'] . ' - Remote ';
					}
				}
				if ($p['LocalDeleted'] > 0 || $p['RemoteDeleted'] > 0) {
					$content .= "\n Deleted: ";
					if ($p['LocalDeleted'] > 0) {
						$content .= $p['LocalDeleted'] . ' - Local ';
					}
					if ($p['RemoteDeleted'] > 0) {
						$content .= $p['RemoteDeleted'] . ' - Remote ';
					}
				}

				$notification->setParsedSubject("Tasks Syncronized \n");
				$notification->setRichMessage($content);

				return $notification;
			default:
				// Unknown subject => Unknown notification => throw
				throw new InvalidArgumentException();
		}
	}
}
