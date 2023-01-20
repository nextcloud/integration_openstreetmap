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
		$maptilerApiKey = $this->config->getAppValue(Application::APP_ID, 'maptiler_api_key');
		$mapboxApiKey = $this->config->getAppValue(Application::APP_ID, 'mapbox_api_key');

		$state = [
			'nominatim_api_key' => $nominatimApiKey,
			'maptiler_api_key' => $maptilerApiKey,
			'mapbox_api_key' => $mapboxApiKey,
		];
		$this->initialStateService->provideInitialState('admin-config', $state);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
