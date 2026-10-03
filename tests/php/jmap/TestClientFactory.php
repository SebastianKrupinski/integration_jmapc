<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tests\Jmap;

use JmapClient\Client;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TestClientFactory {
	public static function InstanceClient(): Client {
		// Load the credentials from the JSON file
		$servicesFile = __DIR__ . '/resources/services.json';
		$servicesData = file_get_contents($servicesFile);
		$servicesData = json_decode($servicesData, true);
		$service = ServiceEntity::fromRow($servicesData[0]);
		return RemoteService::freshClient($service);
	}

	/**
	 * Skips the running test when the Stalwart version given in STALWART_VERSION is
	 * older than its RequiresStalwart version, runs it when the version is unknown
	 */
	public static function checkRequirements(TestCase $test): void {
		$current = getenv('STALWART_VERSION');
		if ($current === false || $current === '') {
			return;
		}
		$method = new \ReflectionMethod($test, $test->name());
		foreach ($method->getAttributes(RequiresStalwart::class) as $attribute) {
			$requirement = $attribute->newInstance();
			if (version_compare(ltrim($current, 'v'), $requirement->version, '<')) {
				Assert::markTestSkipped($requirement->reason);
			}
		}
	}
}
