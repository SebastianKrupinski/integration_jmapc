<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Controller;

use OCA\JMAPC\Service\ConfigurationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class AdminConfigurationController extends Controller {

	/**
	 * @var ConfigurationService
	 */
	private $ConfigurationService;

	public function __construct($appName, IRequest $request, ConfigurationService $ConfigurationService) {

		parent::__construct($appName, $request);

		$this->ConfigurationService = $ConfigurationService;

	}

	/**
	 * handles save configuration requests
	 *
	 * @param array $values key/value pairs to save
	 *
	 * @return DataResponse
	 */
	public function depositConfiguration(array $values): DataResponse {

		$this->ConfigurationService->depositSystem($values);

		return new DataResponse(true);
	}
}
