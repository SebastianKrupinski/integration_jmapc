<?php
/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
use OCP\App\IAppManager;
use OCP\Server;

require_once __DIR__ . '/../../../../../tests/bootstrap.php';

Server::get(IAppManager::class)->loadApps();
OC_Hook::clear();
