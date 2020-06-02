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

namespace OCA\UserStatus\Service;

use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Db\UserStatusMapper;
use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidMessageIdException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Class StatusService
 *
 * @package OCA\UserStatus\Service
 */
class StatusService {

	/** @var UserStatusMapper */
	private $mapper;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var PredefinedStatusService */
	private $predefinedStatusService;

	/** @var EmojiService */
	private $emojiService;

	/** @var string[] */
	private $allowedStatusTypes = [
		'online',
		'idle',
		'dnd',
		'invisible',
		'offline'
	];

	/** @var int */
	private $maximumMessageLength = 80;

	/**
	 * StatusService constructor.
	 *
	 * @param UserStatusMapper $mapper
	 * @param ITimeFactory $timeFactory
	 * @param PredefinedStatusService $defaultStatusService,
	 * @param EmojiService $emojiService
	 */
	public function __construct(UserStatusMapper $mapper,
								ITimeFactory $timeFactory,
								PredefinedStatusService $defaultStatusService,
								EmojiService $emojiService) {
		$this->mapper = $mapper;
		$this->timeFactory = $timeFactory;
		$this->predefinedStatusService = $defaultStatusService;
		$this->emojiService = $emojiService;
	}

	/**
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return UserStatus[]
	 */
	public function findAll(?int $limit = null, ?int $offset = null): array {
		return $this->mapper->findAll($limit, $offset);
	}

	/**
	 * @param string $userId
	 * @return UserStatus
	 * @throws DoesNotExistException
	 */
	public function findByUserId(string $userId):UserStatus {
		return $this->mapper->findByUserId($userId);
	}

	/**
	 * @param string $userId
	 * @param string $status
	 * @param int|null $statusTimestamp
	 * @param bool $isUserDefined
	 * @return UserStatus
	 * @throws InvalidStatusTypeException
	 */
	public function setStatus(string $userId,
							  string $status,
							  ?int $statusTimestamp,
							  bool $isUserDefined): UserStatus {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
		}

		// Check if status-type is valid
		if (!\in_array($status, $this->allowedStatusTypes, true)) {
			throw new InvalidStatusTypeException('Status-type "' . $status . '" is not supported');
		}
		if ($statusTimestamp === null) {
			$statusTimestamp = $this->timeFactory->getTime();
		}

		$userStatus->setStatus($status);
		$userStatus->setStatusTimestamp($statusTimestamp);
		$userStatus->setIsUserDefined($isUserDefined);

		return $this->mapper->insertOrUpdate($userStatus);
	}

	/**
	 * @param string $userId
	 * @param string $messageId
	 * @param int|null $clearAt
	 * @return UserStatus
	 * @throws InvalidMessageIdException
	 * @throws InvalidClearAtException
	 */
	public function setPredefinedMessage(string $userId,
										 string $messageId,
										 ?int $clearAt): UserStatus {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
			$userStatus->setStatus('offline');
			$userStatus->setStatusTimestamp(0);
			$userStatus->setIsUserDefined(false);
		}

		if (!$this->predefinedStatusService->isValidId($messageId)) {
			throw new InvalidMessageIdException('Message-Id "' . $messageId . '" is not supported');
		}

		// Check that clearAt is in the future
		if ($clearAt !== null && $clearAt < $this->timeFactory->getTime()) {
			throw new InvalidClearAtException('ClearAt is in the past');
		}

		$userStatus->setMessageId($messageId);
		$userStatus->setCustomIcon(null);
		$userStatus->setCustomMessage(null);
		$userStatus->setClearAt($clearAt);

		return $this->mapper->insertOrUpdate($userStatus);
	}

	/**
	 * @param string $userId
	 * @param string|null $statusIcon
	 * @param string|null $message
	 * @param int|null $clearAt
	 * @return UserStatus
	 * @throws InvalidClearAtException
	 * @throws InvalidStatusIconException
	 * @throws StatusMessageTooLongException
	 */
	public function setCustomMessage(string $userId,
									 ?string $statusIcon,
									 string $message,
									 ?int $clearAt): UserStatus {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
			$userStatus->setStatus('offline');
			$userStatus->setStatusTimestamp(0);
			$userStatus->setIsUserDefined(false);
		}

		// Check if statusIcon contains only one character
		if ($statusIcon !== null && !$this->emojiService->isValidEmoji($statusIcon)) {
			throw new InvalidStatusIconException('Status-Icon is longer than one character');
		}
		// Check for maximum length of custom message
		if (\mb_strlen($message) > $this->maximumMessageLength) {
			throw new StatusMessageTooLongException('Message is longer than supported length of ' . $this->maximumMessageLength . ' characters');
		}
		// Check that clearAt is in the future
		if ($clearAt !== null && $clearAt < $this->timeFactory->getTime()) {
			throw new InvalidClearAtException('ClearAt is in the past');
		}

		$userStatus->setMessageId(null);
		$userStatus->setCustomIcon($statusIcon);
		$userStatus->setCustomMessage($message);
		$userStatus->setClearAt($clearAt);

		return $this->mapper->insertOrUpdate($userStatus);
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function clearStatus(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$userStatus->setStatus('offline');
		$userStatus->setStatusTimestamp(0);
		$userStatus->setIsUserDefined(false);

		$this->mapper->update($userStatus);
		return true;
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function clearMessage(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$userStatus->setMessageId(null);
		$userStatus->setCustomMessage(null);
		$userStatus->setCustomIcon(null);
		$userStatus->setClearAt(null);

		$this->mapper->update($userStatus);
		return true;
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function removeUserStatus(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$this->mapper->delete($userStatus);
		return true;
	}
}
