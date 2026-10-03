<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Commands;

use OCA\JMAPC\Service\CoreService;
use OCA\JMAPC\Service\ServicesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Disconnect extends Command {

	public function __construct(
		private IUserManager $userManager,
		private CoreService $CoreService,
		private ServicesService $servicesService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('jmapc:disconnect')
			->setDescription('Disconnects a user from an JMAP Server')
			->addArgument('user', InputArgument::REQUIRED, 'User with service(s) to disconnect')
			->addArgument('service', InputArgument::OPTIONAL, 'Service to disconnect');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		$sid = (int)$input->getArgument('service');

		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		if ($sid) {
			$service = $this->servicesService->fetchByUserIdAndServiceId($uid, $sid);
			if (!$service) {
				$output->writeln("<error>Service $sid does not exist</error>");
				return self::INVALID;
			}
			$services[] = $service;
		} else {
			$services = $this->servicesService->fetchByUserId($uid);
		}

		foreach ($services as $service) {
			$sid = $service->getId();
			$serviceName = $service->getName();
			$this->CoreService->disconnectAccount($uid, $sid);
			$output->writeln("<info>Disconnected User $uid from JMAP Server $serviceName</info>");
		}

		return self::SUCCESS;
	}
}
