<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Commands\Files;

use OCA\JMAPC\Service\FilesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCollections extends Command {

	public function __construct(
		private IUserManager $userManager,
		private FilesService $filesService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('jmapc:files:list')
			->setDescription('Show the files collections of a user')
			->addArgument('user', InputArgument::REQUIRED, 'User with configured files collection(s)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		$rows = [];
		foreach ($this->filesService->fetchByUserId($uid) as $collection) {
			$rows[] = [
				$collection->getId(),
				$collection->getSid(),
				$collection->getLabel(),
				$collection->getLocation(),
				$collection->getMode(),
				$collection->getVisible() ? 'yes' : 'no',
			];
		}

		if ($rows === []) {
			$output->writeln("<info>User $uid has no files collections</info>");
			return self::SUCCESS;
		}
		$table = new Table($output);
		$table->setHeaders(['Id', 'Service', 'Label', 'Location', 'Mode', 'Visible'])->setRows($rows);
		$table->render();
		return self::SUCCESS;
	}
}
