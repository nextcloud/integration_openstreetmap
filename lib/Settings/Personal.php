<?php

namespace OCA\Osm\Settings;

use OCA\Osm\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;

use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$searchLocationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_location_enabled', Application::DEFAULT_SEARCH_LOCATION_ENABLED_VALUE) === '1';
		$adminSearchLocationEnabled = $this->config->getAppValue(Application::APP_ID, 'search_location_enabled', Application::DEFAULT_SEARCH_LOCATION_ENABLED_VALUE) === '1';
		$navigationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'navigation_enabled', '0') === '1';
		$linkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
		$preferSimpleOsmIframe = $this->config->getUserValue($this->userId, Application::APP_ID, 'prefer_simple_osm_iframe', '0') === '1';

		$userConfig = [
			'admin_search_location_enabled' => $adminSearchLocationEnabled,
			'search_location_enabled' => $searchLocationEnabled,
			'navigation_enabled' => $navigationEnabled ,
			'link_preview_enabled' => $linkPreviewEnabled,
			'prefer_simple_osm_iframe' => $preferSimpleOsmIframe,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
