<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Controller;

use OCA\JMAPC\Service\ConfigurationService;
use OCA\JMAPC\Service\CoreService;
use OCA\JMAPC\Service\FilesService;
use OCA\JMAPC\Service\HarmonizationService;
use OCA\JMAPC\Service\ServicesService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class UserConfigurationController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigurationService $ConfigurationService,
		private CoreService $CoreService,
		private HarmonizationService $HarmonizationService,
		private ServicesService $ServicesService,
		private FilesService $FilesService,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * handles services list request
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/service/list')]
	public function serviceList(): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// retrieve services
		try {
			$rs = $this->ServicesService->fetchByUserId($this->userId);
			return new DataResponse($rs);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles connect click event
	 *
	 * @param array $service collection of configuration options
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/service/connect')]
	public function Connect(array $service): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// assign options
		$options = ['VALIDATE'];
		// execute command
		try {
			$rs = $this->CoreService->connectAccount($this->userId, $service, $options);
			return new DataResponse('success');
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles disconnect click event
	 *
	 * @param int $sid Service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/service/disconnect')]
	public function Disconnect(int $sid): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// execute command
		try {
			$this->CoreService->disconnectAccount($this->userId, $sid);
			return new DataResponse('success');
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles synchronize click event
	 *
	 * @param int $sid service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/service/harmonize')]
	public function Harmonize(int $sid): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// execute command
		try {
			$this->HarmonizationService->performHarmonization($this->userId, $sid, 'M');
			return new DataResponse('success');
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles remote collections fetch requests
	 *
	 * @param int $sid service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/remote/collections/fetch')]
	public function remoteCollectionsFetch(int $sid): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// retrieve collections
		try {
			$rs = $this->CoreService->remoteCollectionsFetch($this->userId, $sid);
			return new DataResponse($rs);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * handles local collections fetch requests
	 *
	 * @param int $sid Service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/local/collections/fetch')]
	public function localCollectionsFetch(int $sid): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// retrieve collections
		try {
			$rs = $this->CoreService->localCollectionsFetch($this->userId, $sid);
			return new DataResponse($rs);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles save correlations requests
	 *
	 * @param array $values key/value pairs to save
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/local/collections/deposit')]
	public function localCollectionsDeposit(int $sid, array $ContactCorrelations, array $EventCorrelations, array $TaskCorrelations): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// execute command
		try {
			$rs = $this->CoreService->localCollectionsDeposit($this->userId, $sid, $ContactCorrelations, $EventCorrelations, $TaskCorrelations);
			return $this->localCollectionsFetch($sid);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles files collections list request
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/files/collections/list')]
	public function filesCollectionList(): DataResponse {
		return $this->filesResponse(fn () => $this->FilesService->fetchByUserId($this->userId));
	}

	/**
	 * handles files collection create request
	 *
	 * @param int $sid service id
	 * @param string $location mount location relative to the user files root
	 * @param string $mode collection mode
	 * @param string|null $label collection label, defaults to the service label
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/files/collections/create')]
	public function filesCollectionCreate(int $sid, string $location, string $mode = FilesService::MODE_LIVE, ?string $label = null): DataResponse {
		return $this->filesResponse(fn () => $this->FilesService->create($this->userId, $sid, $location, $mode, $label));
	}

	/**
	 * handles files collection modify request, omitted parameters are left unchanged
	 *
	 * @param int $id collection id
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/files/collections/modify')]
	public function filesCollectionModify(int $id, ?string $location = null, ?string $mode = null, ?string $label = null, ?bool $visible = null): DataResponse {
		return $this->filesResponse(fn () => $this->FilesService->modify($this->userId, $id, $location, $mode, $label, $visible));
	}

	/**
	 * handles files collection delete request
	 *
	 * @param int $id collection id
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/files/collections/delete')]
	public function filesCollectionDelete(int $id): DataResponse {
		return $this->filesResponse(function () use ($id) {
			$this->FilesService->delete($this->userId, $id);
			return 'success';
		});
	}

	/**
	 * executes a files operation and converts its result or failure to a response
	 */
	private function filesResponse(callable $operation): DataResponse {
		try {
			return new DataResponse($operation());
		} catch (\InvalidArgumentException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $th) {
			$this->logger->error('Files operation failed', ['exception' => $th]);
			return new DataResponse('Files operation failed', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

}
