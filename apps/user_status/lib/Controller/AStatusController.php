<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\UserStatus\Controller;

use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Service\PredefinedStatusService;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

abstract class AStatusController extends OCSController {

	/** @var PredefinedStatusService */
	protected $defaultStatusService;

	/** @var string|null */
	protected $lang;

	/**
	 * AStatusController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param PredefinedStatusService $predefinedStatusService
	 */
	public function __construct(string $appName,
								IRequest $request,
								PredefinedStatusService $predefinedStatusService) {
		parent::__construct($appName, $request);
		$this->defaultStatusService = $predefinedStatusService;

		// Client language might differ from user language in Nc
		$this->lang = $this->request->getHeader('X-REQUESTED-CLIENT-LANG');
		if ($this->lang === '') {
			$this->lang = null;
		}
	}

	/**
	 * @param UserStatus $status
	 * @return array
	 */
	protected function formatStatus(UserStatus $status): array {
		$icon = $status->getCustomIcon();
		$message = $status->getCustomMessage();
		if ($status->getMessageId() !== null) {
			$icon = $this->defaultStatusService->getIconForId($status->getMessageId());
			$message = $this->defaultStatusService->getTranslatedStatusForId($this->lang, $status->getMessageId());
		}

		$visibleStatus = $status->getStatus();
		if ($visibleStatus === 'invisible') {
			$visibleStatus = 'offline';
		}

		return [
			'userId' => $status->getUserId(),
			'message' => $message,
			'icon' => $icon,
			'clearAt' => $status->getClearAt(),
			'status' => $visibleStatus,
		];
	}

	/**
	 * @param UserStatus $status
	 * @return array
	 */
	protected function formatPrivateStatus(UserStatus $status): array {
		$formattedStatus = $this->formatStatus($status);

		// Expose 'invisible'
		$formattedStatus['status'] = $status->getStatus();
		// Expose whether or not current status was user-defined or automatically detected
		$formattedStatus['statusIsUserDefined'] = $status->getIsUserDefined();
		// Expose predefined message ids
		$formattedStatus['messageIsPredefined'] = $status->getMessageId() !== null;
		$formattedStatus['messagePredefinedMessageId'] = $status->getMessageId();

		return $formattedStatus;
	}
}
