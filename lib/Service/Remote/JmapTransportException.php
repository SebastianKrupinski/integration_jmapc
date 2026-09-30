<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Service\Remote;

use Psr\Http\Client\ClientExceptionInterface;

class JmapTransportException extends \RuntimeException implements ClientExceptionInterface {
}
