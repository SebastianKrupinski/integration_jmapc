<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Commands\Files;

use InvalidArgumentException;
use OCA\JMAPC\Service\FilesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCollection extends Command {

	public function __construct(
		private IUserManager $userManager,
		private FilesService $filesService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('jmapc:files:create')
			->setDescription('Mount the files of a service in a user\'s files')
			->addArgument('user', InputArgument::REQUIRED, 'User owning the service')
			->addArgument('service', InputArgument::REQUIRED, 'Service to mount')
			->addArgument('location', InputArgument::REQUIRED, 'Mount location relative to the user files root')
			->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Collection mode', FilesService::MODE_LIVE)
			->addOption('label', null, InputOption::VALUE_REQUIRED, 'Collection label, defaults to the service label');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		try {
			$collection = $this->filesService->create(
				$uid,
				(int)$input->getArgument('service'),
				$input->getArgument('location'),
				$input->getOption('mode'),
				$input->getOption('label'),
			);
		} catch (InvalidArgumentException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

		$output->writeln('<info>Created files collection ' . $collection->getId() . ' mounted at ' . $collection->getLocation() . '</info>');
		return self::SUCCESS;
	}
}
