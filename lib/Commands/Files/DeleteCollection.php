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
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCollection extends Command {

	public function __construct(
		private IUserManager $userManager,
		private FilesService $filesService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('jmapc:files:delete')
			->setDescription('Remove a files collection, remote data is not touched')
			->addArgument('user', InputArgument::REQUIRED, 'User owning the files collection')
			->addArgument('collection', InputArgument::REQUIRED, 'Files collection to remove');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		$id = (int)$input->getArgument('collection');
		try {
			$this->filesService->delete($uid, $id);
		} catch (InvalidArgumentException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

		$output->writeln("<info>Deleted files collection $id</info>");
		return self::SUCCESS;
	}
}
