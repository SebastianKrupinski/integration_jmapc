<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Tasks;

use OCA\JMAPC\Service\ConfigurationService;
use OCA\JMAPC\Service\HarmonizationService;
use OCA\JMAPC\Service\HarmonizationThreadService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class HarmonizationLauncher extends TimedJob {

	public function __construct(
		protected ITimeFactory $time,
		private LoggerInterface $logger,
		private ConfigurationService $ConfigurationService,
		private HarmonizationService $HarmonizationService,
		private HarmonizationThreadService $HarmonizationThreadService,
	) {
		parent::__construct($time);

		$this->setInterval(300);
	}

	protected function run($arguments) {
		// extract user id
		$uid = $arguments['uid'];
		// evaluate harmonization mode
		// active mode
		if ($this->ConfigurationService->getHarmonizationMode() == 'A') {
			try {

				// retrieve thread id
				$tid = $this->HarmonizationThreadService->getId($uid);
				// evaluate if thread is live and launch new thread if needed
				if (!$this->HarmonizationThreadService->isActive($uid, $tid)) {
					// launch new thread
					$tid = $this->HarmonizationThreadService->launch($uid);
				}

				if ($tid > 0) {
					$this->HarmonizationThreadService->setId($uid, $tid);
					$this->HarmonizationThreadService->setHeartBeat($uid, time());
				}

			} catch (\Throwable $e) {
				$this->logger->error("Harmonization launcher encountered an error while starting a thread for $uid", ['app' => 'integration_jmapc', 'exception' => $e]);
			} catch (\Exception $e) {
				$this->logger->error("Harmonization launcher encountered an error while starting a thread for $uid", ['app' => 'integration_jmapc', 'exception' => $e]);
			}
		}
		// passive mode
		else {
			try {

				$this->HarmonizationService->performHarmonization($uid);

			} catch (\Throwable $e) {
				$this->logger->error("Harmonization launcher encountered an error while harmonizing for $uid", ['app' => 'integration_jmapc', 'exception' => $e]);
			} catch (\Exception $e) {
				$this->logger->error("Harmonization launcher encountered an error while harmonizing for $uid", ['app' => 'integration_jmapc', 'exception' => $e]);
			}
		}
	}
}
