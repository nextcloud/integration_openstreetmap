<?php
namespace OCA\Osm\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Osm\AppInfo\Application;

class Admin implements ISettings {

	private IConfig $config;
	private IInitialState $initialStateService;
	private ?string $userId;

	public function __construct(IConfig       $config,
								IInitialState $initialStateService,
								?string       $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$nominatimApiKey = $this->config->getAppValue(Application::APP_ID, 'nominatim_api_key');

		$userConfig = [
			'nominatim_api_key' => $nominatimApiKey,
		];
		$this->initialStateService->provideInitialState('admin-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
