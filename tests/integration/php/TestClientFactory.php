<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\JMAPC\Tests\Integration;

use JmapClient\Client;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;

class TestClientFactory
{
    public static function InstanceClient(): Client
    {
         // Load the credentials from the JSON file
        $servicesFile = __DIR__ . '/../resources/services.json';
        $servicesData = file_get_contents($servicesFile);
        $servicesData = json_decode($servicesData, true);
        $service = new ServiceEntity();
        $service->fromRow($servicesData[0]);
        return RemoteService::freshClient($service);
    }
}