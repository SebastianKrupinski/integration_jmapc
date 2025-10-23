<?php
declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\JMAPC\Commands;

use JmapClient\Client as JmapClient;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Service\ServicesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command {

	public function __construct(
		private IUserManager $userManager,
		private ServicesService $servicesService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('jmapc:test')
			->setDescription('Test configured services')
			->addArgument('user', InputArgument::REQUIRED, 'User with service')
			->addArgument('service', InputArgument::REQUIRED, 'Service to test');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		$sid = (int)$input->getArgument('service');

		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		$service = $this->servicesService->fetchByUserIdAndServiceId($uid, $sid);
		if (!$service) {
			$output->writeln("<error>Service $sid does not exist</error>");
			return self::INVALID;
		}

		$client = RemoteService::freshClient($service);

		$this->testMail($client, $output);
		$this->testContacts($client, $output);
		$this->testEvents($client, $output);
		$this->testTasks($client, $output);
		
		return self::SUCCESS;
	}

	private function testMail(JmapClient $client, OutputInterface $output): void {
		$remoteService = RemoteService::mailService($client);
		try {
			$list = $remoteService->collectionList();
			$count = count($list);
			$output->writeln("Mail: <info>Supported $count collections found</info>");
		} catch (\Exception $e) {
			$output->writeln("Mail: <error>Not Support</error>");
		}
	}

	private function testContacts(JmapClient $client, OutputInterface $output): void {
		$remoteService = RemoteService::contactsService($client);
		try {
			$list = $remoteService->collectionList();
			$count = count($list);
			$output->writeln("Contacts: <info>Supported $count collections found</info>");
		} catch (\Exception $e) {
			$output->writeln("Contacts: <error>Not Support</error>");
		}
	}

	private function testEvents(JmapClient $client, OutputInterface $output): void {
		$remoteService = RemoteService::eventsService($client);
		try {
			$list = $remoteService->collectionList();
			$count = count($list);
			$output->writeln("Events: <info>Supported $count collections found</info>");
		} catch (\Exception $e) {
			$output->writeln("Events: <error>Not Support</error>");
		}
	}

	private function testTasks(JmapClient $client, OutputInterface $output): void {
		$remoteService = RemoteService::tasksService($client);
		try {
			$list = $remoteService->collectionList();
			$count = count($list);
			$output->writeln("Tasks: <info>Supported $count collections found</info>");
		} catch (\Exception $e) {
			$output->writeln("Tasks: <error>Not Support</error>");
		}
	}

}
