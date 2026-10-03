<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Service;

use OCA\JMAPC\Store\Local\ServicesTemplateStore;

class ServicesTemplateService {
	private ServicesTemplateStore $_Store;

	public function __construct(ServicesTemplateStore $store) {

		$this->_Store = $store;

	}

	public function findByDomain(string $domain): array {

		return $this->_Store->fetchByDomain($domain);
	}

}
