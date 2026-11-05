<?php

/**
 * Nextcloud - OpenStreetMap
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Osm\Controller;

use OCA\Osm\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Config\IUserConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IUserConfig $userConfig,
		private IAppConfig $appConfig,
		private ?string $userId,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->userConfig->setValueString($this->userId, Application::APP_ID, $key, $value);
		}
		return new DataResponse('');
	}

	/**
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			if (in_array($key, ['maptiler_api_key'], true)) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
			$oldValue = $this->appConfig->hasKey(Application::APP_ID, $key)
				? $this->appConfig->getValueString(Application::APP_ID, $key)
				: null;
			$this->appConfig->setValueString(Application::APP_ID, $key, $value);
			if ($oldValue === $value) {
				continue;
			}
			$this->audit(
				'The OpenStreetMap admin setting "%s" was changed from "%s" to "%s" by "%s"',
				[
					'setting' => $key,
					'oldValue' => $oldValue,
					'newValue' => $value,
					'user' => $this->userId,
				],
				'openstreetmap.admin_setting.updated',
			);
		}

		return new DataResponse([]);
	}

	/**
	 * @param array $values
	 * @return DataResponse
	 */
	#[PasswordConfirmationRequired]
	public function setSensitiveAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$oldValue = $this->appConfig->hasKey(Application::APP_ID, $key)
				? $this->appConfig->getValueString(Application::APP_ID, $key)
				: null;
			$this->appConfig->setValueString(Application::APP_ID, $key, $value, false, true);
			if ($oldValue === $value) {
				continue;
			}
			// keep the values out of the audit log as they are sensitive
			$this->audit(
				'The sensitive OpenStreetMap admin setting "%s" was changed from %s to %s by "%s"',
				[
					'setting' => $key,
					'oldValue' => $oldValue === null ? 'unset' : 'a redacted value',
					'newValue' => $value === '' ? 'unset' : 'a redacted value',
					'user' => $this->userId,
				],
				'openstreetmap.admin_setting.updated',
				true,
			);
		}

		return new DataResponse([]);
	}

	/**
	 * Log a critical action to the admin audit log
	 *
	 * The `$operation` parameter of `CriticalActionPerformedEvent` only exists since Nextcloud 36;
	 * on Nextcloud 35 the extra constructor argument is silently ignored by PHP. The app's pinned
	 * OCP stubs predate the extra parameter, hence the psalm suppression.
	 */
	private function audit(
		string $message,
		array $params,
		string $operation,
		bool $obfuscateParameters = false,
	): void {
		/** @psalm-suppress TooManyArguments */
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			$message,
			$params,
			$obfuscateParameters,
			$operation,
		));
	}
}
