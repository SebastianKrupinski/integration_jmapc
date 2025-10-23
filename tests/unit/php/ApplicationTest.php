<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Unit;

use OCA\JMAPC\AppInfo\Application;
use Test\TestCase;

class ApplicationTest extends TestCase {

	public function testApplicationId(): void {
		self::assertEquals(Application::APP_ID, 'integration_jmapc');
	}
	
	public function testApplicationName(): void {
		self::assertEquals(Application::APP_TAG, 'JMAPC');
	}

	public function testApplicationVersion(): void {
		self::assertEquals(Application::APP_LABEL, 'JMAP Client');
	}
	
}
