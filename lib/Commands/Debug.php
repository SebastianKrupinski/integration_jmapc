<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Commands;

use OCA\JMAPC\Service\ServicesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @psalm-api
 */
class Debug extends Command {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly ServicesService $servicesService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('jmapc:debug')
			->setDescription('Enable or disable transmission logging for a user service')
			->setHelp(<<<'HELP'
Enable or disable transmission logging for one or more configured services.

Examples:
  php occ jmapc:debug alice
  php occ jmapc:debug alice 4
  php occ jmapc:debug alice --disable
  php occ jmapc:debug alice 4 --disable
HELP)
			->addArgument('user', InputArgument::REQUIRED, 'User with configured service(s)')
			->addArgument('service', InputArgument::OPTIONAL, 'Service ID to update logging for')
			->addOption('enable', null, InputOption::VALUE_NONE, 'Enable logging')
			->addOption('disable', null, InputOption::VALUE_NONE, 'Disable logging');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = (string)$input->getArgument('user');
		$serviceArgument = $input->getArgument('service');
		$enable = (bool)$input->getOption('enable');
		$disable = (bool)$input->getOption('disable');

		if ($enable && $disable) {
			$output->writeln('<error>Use either --enable or --disable, not both.</error>');
			return self::INVALID;
		}

		$debug = !$disable;

		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		if ($serviceArgument !== null) {
			$sid = filter_var($serviceArgument, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			if ($sid === false) {
				$output->writeln('<error>Service ID must be a positive integer.</error>');
				return self::INVALID;
			}
			$service = $this->servicesService->fetchByUserIdAndServiceId($uid, $sid);
			if ($service === null) {
				$output->writeln("<error>Service $sid does not exist</error>");
				return self::INVALID;
			}
			$services = [$service];
		} else {
			$services = $this->servicesService->fetchByUserId($uid);
		}

		if ($services === []) {
			$output->writeln("<error>User $uid has no configured services</error>");
			return self::INVALID;
		}

		foreach ($services as $service) {
			$service->setDebug($debug);
			$this->servicesService->modify($uid, $service);

			$serviceId = (int)$service->getId();
			$serviceName = $service->getLabel() ?: $service->getLocationHost();
			$output->writeln(sprintf(
				'<info>%s logging for service %d (%s)</info>',
				$debug ? 'Enabled' : 'Disabled',
				$serviceId,
				$serviceName,
			));
		}

		return self::SUCCESS;
	}
}
