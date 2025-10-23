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

use OCA\JMAPC\Service\ServicesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Show extends Command {

	public function __construct(
		private IUserManager $userManager,
		private ServicesService $servicesService
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('jmapc:show')
			->setDescription('Show all configured services')
			->addArgument('user', InputArgument::OPTIONAL, 'User with configured service(s)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');

		if ($uid !== null) {
			if (!$this->userManager->userExists($uid)) {
				$output->writeln("<error>User $uid does not exist</error>");
				return self::INVALID;
			}
			$services = $this->servicesService->fetchByUserId($uid);
		} else {
			$services = $this->servicesService->list();
		}

		$list = [];
		foreach ($services as $service) {
			$list[] = [
				$service->getUid(),
				$service->getId(),
				$service->getLabel(),
				$service->getLocationHost(),
				$service->getLocationPort(),
				$service->getAuth(),
				$service->getEnabled() ? 'yes' : 'no',
				$service->getConnected() ? 'yes' : 'no',
				$service->getDebug() ? 'yes' : 'no',
				$service->getHarmonizationEnd() ? date('Y-m-d H:i:s', $service->getHarmonizationEnd()) : 'no',
				$service->getMailMode() ? $service->getMailMode() : 'Cached',
				$service->getEventsMode() ? $service->getEventsMode() : 'Cached',
				$service->getContactsMode() ? $service->getContactsMode() : 'Cached',
				$service->getTasksMode() ? $service->getTasksMode() : 'Cached',
			];
		}

		if (count($list) > 0) {
			$table = new Table($output);
			$table->setHeaders(['User', 'Id', 'Label', 'Host', 'Port', 'Auth', 'Enabled', 'Connected', 'Debug', 'Harmonized', 'Mail Mode', 'Events Mode', 'Contacts Mode', 'Tasks Mode'])->setRows($list);
			$table->render();
		} else {
			$output->writeln("<info>User $uid has no configured services</info>");
		}
		return self::SUCCESS;
	}
}
