<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Commands;

use OCA\JMAPC\Service\CoreService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Connect extends Command {

	public function __construct(
		private IUserManager $userManager,
		private CoreService $CoreService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('jmapc:connect')
			->setDescription('Connects a user to JMAP Server')
			->addArgument('user',
				InputArgument::REQUIRED,
				'User whom to connect to the JMAP Server')
			->addArgument('provider',
				InputArgument::REQUIRED,
				'FQDN or IP address of the JMAP Server')
			->addArgument('accountid',
				InputArgument::REQUIRED,
				'The username of the account to connect to on the JMAP Server')
			->addArgument('accountsecret',
				InputArgument::REQUIRED,
				'The password of the account to connect to on the JMAP Server')
			->addArgument('validate',
				InputArgument::OPTIONAL,
				'Should we validate the credentials with JMAP Server. (default true)');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		$account_provider = $input->getArgument('provider');
		$account_bauth_id = $input->getArgument('accountid');
		$account_bauth_secret = $input->getArgument('accountsecret');
		$validate = filter_var($input->getArgument('validate'), FILTER_VALIDATE_BOOLEAN);
		$flags = [];

		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		if ($validate) {
			$flags = ['VALIDATE'];
		}

		$this->CoreService->connectAccount($uid, $account_bauth_id, $account_bauth_secret, $account_provider, $flags);

		$output->writeln("<info>User $uid connected to $account_provider as $account_bauth_id</info>");

		return self::SUCCESS;
	}
}
