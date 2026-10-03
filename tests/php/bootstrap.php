<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require dirname(__DIR__, 4) . '/tests/bootstrap.php';

\OC::$composerAutoloader->addPsr4('OCA\\JMAPC\\', dirname(__DIR__, 2) . '/lib');
\OC::$composerAutoloader->addPsr4('OCA\\JMAPC\\Tests\\Unit\\', __DIR__ . '/unit');
\OC::$composerAutoloader->addPsr4('OCA\\JMAPC\\Tests\\Database\\', __DIR__ . '/database');
\OC::$composerAutoloader->addPsr4('OCA\\JMAPC\\Tests\\Jmap\\', __DIR__ . '/jmap');

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (isset($_SERVER['APP_DEBUG']) && $_SERVER['APP_DEBUG']) {
	umask(0000);
}
