<?php

namespace OCA\Osm\Settings;

use OCA\Osm\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;

use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private IInitialState $initialStateService,
		?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$proxyOsm = $this->appConfig->getValueString(Application::APP_ID, 'proxy_osm', Application::DEFAULT_PROXY_OSM_VALUE) === '1';
		$searchLocationEnabled = $this->appConfig->getValueString(Application::APP_ID, 'search_location_enabled', Application::DEFAULT_SEARCH_LOCATION_ENABLED_VALUE) === '1';
		$maptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key');

		$state = [
			'proxy_osm' => $proxyOsm,
			'search_location_enabled' => $searchLocationEnabled,
			// don't expose the API key to the user
			'maptiler_api_key' => $maptilerApiKey === '' ? '' : 'dummyApiKey',
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
