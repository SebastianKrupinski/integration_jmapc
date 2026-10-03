<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Unit;

use OC\App\AppStore\Fetcher\AppFetcher;
use OC\Files\AppData\Factory;
use OC\Files\Config\MountProviderCollection;
use OC\Files\Mount\CacheMountProvider;
use OC\Files\Mount\LocalHomeMountProvider;
use OC\Files\Mount\RootMountProvider;
use OC\Files\ObjectStore\PrimaryObjectStoreConfig;
use OC\Files\SetupManager;
use OC\Installer;
use OC\Updater;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Server;

/**
 * Base test case that leaves the instance data intact.
 *
 * The tests run against the configured instance. The core teardown empties the
 * share, storage and filecache tables and deletes every folder in the data
 * directory, so this teardown keeps only its non-destructive steps. Tests must
 * remove the rows and files they create themselves.
 */
abstract class TestCase extends \Test\TestCase {

	#[\Override]
	public static function tearDownAfterClass(): void {
		if (self::$realDatabase !== null) {
			// a test without database access replaced the connection, restore it
			/** @psalm-suppress InternalMethod */
			\OC::$server->registerService(IDBConnection::class, static fn () => self::$realDatabase);
		}
		$db = Server::get(IDBConnection::class);
		if ($db->inTransaction()) {
			$db->rollBack();
			throw new \Exception('There was a transaction still in progress and needed to be rolled back. Please fix this in your test.');
		}

		self::tearDownAfterClassCleanStrayHooks();
		self::tearDownAfterClassCleanStrayLocks();

		/** @psalm-suppress InternalMethod */
		\OC::$server->removeFromInternalContainer(Factory::class);
		/** @psalm-suppress InternalMethod */
		\OC::$server->removeFromInternalContainer(AppFetcher::class);
		/** @psalm-suppress InternalMethod */
		\OC::$server->removeFromInternalContainer(Installer::class);
		/** @psalm-suppress InternalMethod */
		\OC::$server->removeFromInternalContainer(Updater::class);

		$setupManager = Server::get(SetupManager::class);
		$setupManager->tearDown();

		$mountProviderCollection = Server::get(MountProviderCollection::class);
		$mountProviderCollection->clearProviders();
		$config = Server::get(IConfig::class);
		$mountProviderCollection->registerProvider(new CacheMountProvider($config));
		$mountProviderCollection->registerHomeProvider(new LocalHomeMountProvider());
		$mountProviderCollection->registerRootProvider(new RootMountProvider(Server::get(PrimaryObjectStoreConfig::class), $config));

		$setupManager->setupRoot();

		\PHPUnit\Framework\TestCase::tearDownAfterClass();
	}
}
