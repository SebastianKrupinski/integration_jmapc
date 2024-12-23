<?php
//declare(strict_types=1);

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

require_once __DIR__ . '/../../../../lib/versioncheck.php';

use OCP\IConfig;
use OCP\Server;
use Psr\Log\LoggerInterface;

try {

	require_once __DIR__ . '/../../../../lib/base.php';

	// assign defaults
	$executionMode = 'S';
	$uid = null;

	$logger = Server::get(LoggerInterface::class);
	$config = Server::get(IConfig::class);

	// evaluate if script was started from console
	if (php_sapi_name() == 'cli') {
		$executionMode = 'C';
		$logger->info('Test running, as console script', ['app' => 'integration_jmapc']);
		echo 'Test running, as console script' . PHP_EOL;
	}

	// evaluate running mode
	if ($executionMode == 'C') {
		// retrieve passed parameters
		$parameters = getopt("u:");
		// evaluate if user name exists
		if (isset($parameters["u"])) {
			// assign user name
			//$uid = \OCA\JMAPC\Utile\Sanitizer::username($parameters["u"]);
			$uid = $parameters["u"];
		}
	}
	else {
		// evaluate if user name exists
		if (isset($_GET["u"])) {
			// assign user name
			$uid = \OCA\JMAPC\Utile\Sanitizer::username($_GET["u"]);
		}
	}
	
	// evaluate, if user name is present
	if (empty($uid)) {
		$logger->info('Test ended, missing required parameters', ['app' => 'integration_jmapc']);
		echo 'Test ended, missing required parameters' . PHP_EOL;
		exit(0);
	}

	$logger->info('Test started for ' . $uid, ['app' => 'integration_jmapc']);
	echo 'Test started for ' . $uid . PHP_EOL;

	// load all apps to get all api routes properly setup
	//OC_App::loadApps();
	
	// initialize required services
	//$ConfigurationService = Server::get(\OCA\JMAPC\Service\ConfigurationService::class);
	//$CoreService = Server::get(\OCA\JMAPC\Service\CoreService::class);
	$HarmonizationService = Server::get(\OCA\JMAPC\Service\HarmonizationService::class);
	// $RemoteCommonService = Server::get(\OCA\JMAPC\Service\Remote\RemoteCommonService::class);
	// $RemoteEventsService = Server::get(\OCA\JMAPC\Service\Remote\RemoteEventsService::class);
	
	// execute initial harmonization
	$HarmonizationService->performHarmonization($uid, 7, 'S');

	exit;

	$MailManager = Server::get(\OC\Mail\Provider\Manager::class);
	// test types
	$types = $MailManager->types();
	// test providers
	$providers = $MailManager->providers();
	// test service list
	$services = $MailManager->services('user1');
	// test service find
	$service = $MailManager->findServiceByAddress('user1', 'user1@testmail.com');

	/*
	$client = new \JmapClient\Client();
	$client->configureTransportMode($service->getLocation()->getScheme());
	$client->setHost($service->getLocation()->getHost() . ':' . $service->getLocation()->getPort());

	if ($service->getIdentity()->type() == 'OAUTH') {
		$client->setAuthentication(new \JmapClient\Authentication\Bearer($service->getIdentity()->getAccessId(), $service->getIdentity()->getAccessToken(), $service->getIdentity()->getAccessExpiry()));
	}

	if ($service->getIdentity()->type() == 'BAUTH') {
		$client->setAuthentication(new \JmapClient\Authentication\Basic($service->getIdentity()->getIdentity(), $service->getIdentity()->getSecret()));
	}

	// connect client
	$client->connect();
	$account = 'b0';
	*/

	/*
	// construct query request
	$r0 = new \JmapClient\Requests\Mail\MailboxQuery($account);
	$r0->filter()->in('');
	$r0->sort()->name(true);
	*/

	/*
	// construct get request
	$r1 = new \JmapClient\Requests\Mail\MailboxGet($account);
	// set target to query request
	$r1->target('1');
	*/

	/*
	// open/read file
	$file = file_get_contents(__DIR__ . '/message-001.eml', true);
	//$file = fopen(__DIR__ . '/message-001.eml', 'r');
	// upload blob
	$data = $client->upload($account, 'text/plain', $file);
	// close file
	//fclose($file);
	// convert response to object
	$data = json_decode($data, true);
	*/

	/*
	// construct prase request
	$r0 = new \JmapClient\Requests\Mail\MailParse($account);
	// set target to query request
	$r0->target($data['blobId']);
	$r0->property("id", "blobId", "threadId", "mailboxIds", "keywords", "size",
		"receivedAt", "messageId", "inReplyTo", "references", "sender", "from",
		"to", "cc", "bcc", "replyTo", "subject", "sentAt", "hasAttachment",
		"attachments", "preview", "bodyStructure", "bodyValues");

	// transmit request and receive response
	$bundle = $client->perform([$r0]);
	// extract response
	$response = $bundle->response(0);
	// convert json objects to collection objects
	$list = $response->objects();
	*/

	/*
	// construct set request
	$r0 = new \JmapClient\Requests\Mail\MailSet($account);
	$m0 = $r0->create('1');
	$m0->subject('This is a test');
	$p1 = $m0->structure();
	$p1->type('multipart/mixed');
	$sp1 = $p1->subPart();
	$sp1->type('multipart/alternative');
	*/
	
	//exit;

	/*
	$data = file_get_contents(__DIR__ . '/MessageWithAttachment.json', true);

	$data = json_decode($data, true);

	$message = $service->initiateMessage();
	$message->setParameters($data);

	$content = $message->getContents();

	$type = $content->getType();
	*/

	//exit;
	
	// retrieve all collections information
	$collectionList = $service->collectionList('', '');
	// retrieve single collection information
	$collectionFetch = $service->collectionFetch('', $collectionList[0]->id());
	// search collection inside collection 'a'
	$collectionSearch1 = $service->collectionSearch('', 'Inbox', '');
	$collectionSearch2 = $service->collectionSearch('', 'Drafts', '');
	$collectionSearch3 = $service->collectionSearch('', 'Junk Mail', '');
	if (empty($collectionSearch3)) {
		$collectionSearch3 = $service->collectionSearch('', 'Spam', '');
	}
	
	/*
	*	Test mail box manipulation - Create, Update, Delete, Move
	*/
	// create collection inside inbox
	//$collectionCreated = $service->collectionCreate($collectionSearch1[0]->id(), 'This is a test bvmnxbmvcmx');
	// move collection from inbox to drafts
	//$collectionMoved = $service->collectionMove($collectionSearch1[0]->id(), $collectionCreated, $collectionSearch2[0]->id());
	// update collection inside drafts
	//$collectionUpdated = $service->collectionUpdate($collectionSearch2[0]->id(), $collectionCreated, 'This is a test 20240101');
	// delete collection inside drafts
	//$collectionDeleted = $service->collectionDelete($collectionSearch2[0]->id(), $collectionCreated);

	/*
	*	Test message retrieval - List, Search, Fetch
	*/
	// construct range object
	$range = new \OCA\JMAPC\Providers\RangeAbsolute(0, 1);
	// list messages inside inbox without range
	$entityListNoRange = $service->entityList($collectionSearch1[0]->id());
	// list messages inside inbox with range
	$entityListWithRange = $service->entityList($collectionSearch1[0]->id(), $range);
	// find message inside inbox
	$entitySearch = $service->entitySearch($collectionSearch1[0]->id(), ['text' => 'test']);
	// retrieve message from inbox
	if (!empty($entitySearch)) {
		$entityFetch = $service->entityFetch($entityListWithRange[0]->in()[0], $entityListWithRange[0]->id());
		$entityFetchPlain = $entityFetch->getBodyPlain();
		$entityFetchPlain = $entityFetch->getBodyHtml();
	}
	// retrieve attachment/blob
	foreach ($entityListNoRange as $entry) {
		if (count($entry->getAttachments()) > 0) {
			$attachmentFetch = $service->blobFetch($entry->getAttachments()[0]->id());
			break;
		}
	}

	/*
	*	Test message manipulation - Create, Update, Move
	*/
	// create new message
	$messageCreate = $service->initiateMessage();
	// create new message
	$messageCreate->setFrom(new \OCP\Mail\Provider\Address('joe@example.com', 'Joe Bloggs'));
	$messageCreate->setSubject('World domination');
	$messageCreate->setBodyPlain('I have the most brilliant plan. Let me tell you all about it. What we do is, we');
	$messageCreate->getParameters();
	// create message in drafts
	$entityCreate = $service->entityCreate($collectionSearch2[0]->id(), $messageCreate);
	// fetch created message
	$messageUpdate = $service->entityFetch($collectionSearch2[0]->id(), $entityCreate);
	// update subject of message
	//$messageUpdate->setSubject('World domination Modified');
	// update message in drafts
	//////// $entityUpdate = $service->entityUpdate($collectionSearch2[0]->id(), $entityCreate, $messageUpdate);
	// move message to junk mail
	$entityMoved = $service->entityMove($collectionSearch2[0]->id(), $entityCreate, $collectionSearch3[0]->id());


	/*
	*	Test message manipulation - Create, Update, Move
	*/
	// import message to inbox
	$entityCreate = $service->entityCreate($collectionSearch2[0]->id(), $messageCreate);


	/*
	*	Test message sending
	*/
	// create new message
	$messageSend = $service->initiateMessage();
	$messageSend->setFrom(new \OCP\Mail\Provider\Address('user1@testmail.com', 'User 1'));
	$messageSend->setTo(new \OCP\Mail\Provider\Address('user2@testmail.com', 'User 2'));
	$messageSend->setSubject('World domination');
	$messageSend->setBodyPlain('I have the most brilliant plan. Let me tell you all about it. What we do is, we');
	$messageSend->setBodyHtml('<b>I have the most brilliant plan. Let me tell you all about it. What we do is, we</b>');
	$messageSend->setAttachments($messageSend->newAttachment('This is our great plan', 'plan.txt', 'text/plain'));
	$messageSent = $service->entitySend($messageSend);

	exit;

} catch (\Throwable $e) {
	//$logger->logException($ex, ['app' => 'integration_jmapc']);
	//$logger->info('Test ended unexpectedly', ['app' => 'integration_jmapc']);
	echo $e . PHP_EOL;
	exit(1);
}
