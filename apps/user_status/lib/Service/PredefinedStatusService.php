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

use OCA\UserStatus\AppInfo\Application;
use OCP\L10N\IFactory;

/**
 * Class DefaultStatusService
 *
 * We are offering a set of default statuses, so we can
 * translate them into different languages.
 *
 * @package OCA\UserStatus\Service
 */
class PredefinedStatusService {
	private const MEETING = 'meeting';
	private const COMMUTING = 'commuting';
	private const SICK_LEAVE = 'sick-leave';
	private const VACATIONING = 'vacationing';
	private const REMOTE_WORK = 'remote-work';

	/** @var IFactory */
	private $l10nFactory;

	/**
	 * DefaultStatusService constructor.
	 *
	 * @param IFactory $l10nFactory
	 */
	public function __construct(IFactory $l10nFactory) {
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * @param string|null $lang
	 * @return array
	 */
	public function getDefaultStatuses(?string $lang=null): array {
		$l10n = $this->l10nFactory->get(Application::APP_ID, $lang);

		return [
			[
				'id' => self::MEETING,
				'icon' => '📅',
				'message' => $l10n->t('In a meeting'),
				'clearAt' => [
					'type' => 'period',
					'time' => 3600,
				],
			],
			[
				'id' => self::COMMUTING,
				'icon' => '🚌',
				'message' => $l10n->t('Commuting'),
				'clearAt' => [
					'type' => 'period',
					'time' => 1800,
				],
			],
			[
				'id' => self::SICK_LEAVE,
				'icon' => '🤒',
				'message' => $l10n->t('Out sick'),
				'clearAt' => [
					'type' => 'end-of',
					'time' => 'day',
				],
			],
			[
				'id' => self::VACATIONING,
				'icon' => '🌴',
				'message' => $l10n->t('Vacationing'),
				'clearAt' => null,
			],
			[
				'id' => self::REMOTE_WORK,
				'icon' => '🏡',
				'message' => $l10n->t('Working remotely'),
				'clearAt' => [
					'type' => 'end-of',
					'time' => 'day',
				],
			],
		];
	}

	/**
	 * @param string $id
	 * @return string|null
	 */
	public function getIconForId(string $id): ?string {
		switch ($id) {
			case self::MEETING:
				return '📅';

			case self::COMMUTING:
				return '🚌';

			case self::SICK_LEAVE:
				return '🤒';

			case self::VACATIONING:
				return '🌴';

			case self::REMOTE_WORK:
				return '🏡';

			default:
				return null;
		}
	}

	/**
	 * @param string $lang
	 * @param string $id
	 * @return string|null
	 */
	public function getTranslatedStatusForId(string $lang, string $id): ?string {
		$l10n = $this->l10nFactory->get(Application::APP_ID, $lang);

		switch ($id) {
			case self::MEETING:
				return $l10n->t('In a meeting');

			case self::COMMUTING:
				return $l10n->t('Commuting');

			case self::SICK_LEAVE:
				return $l10n->t('Out sick');

			case self::VACATIONING:
				return $l10n->t('Vacationing');

			case self::REMOTE_WORK:
				return $l10n->t('Working remotely');

			default:
				return null;
		}
	}

	/**
	 * @param string $id
	 * @return bool
	 */
	public function isValidId(string $id): bool {
		return \in_array($id, [
			self::MEETING,
			self::COMMUTING,
			self::SICK_LEAVE,
			self::VACATIONING,
			self::REMOTE_WORK,
		], true);
	}
}
