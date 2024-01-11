<?php
/**
 * @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Osm\Listener;

use OCA\Osm\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class OsmReferenceListener implements IEventListener {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialState,
		private ?string $userId
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		$maptilerApiKey = $this->config->getAppValue(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$userConfig = [
			'maptiler_api_key' => $maptilerApiKey,
		];
		$this->initialState->provideInitialState('api-keys', $userConfig);

		$preferSimpleOsmIframe = $this->config->getUserValue($this->userId, Application::APP_ID, 'prefer_simple_osm_iframe', '0') === '1';
		$this->initialState->provideInitialState('prefer-osm-frame', $preferSimpleOsmIframe);
		$proxyOsm = $this->config->getAppValue(Application::APP_ID, 'proxy_osm', Application::DEFAULT_PROXY_OSM_VALUE) === '1';
		$this->initialState->provideInitialState('proxy-osm', $proxyOsm);

		$lastLat = $this->config->getUserValue($this->userId, Application::APP_ID, 'lat');
		$lastLon = $this->config->getUserValue($this->userId, Application::APP_ID, 'lon');
		$lastZoom = $this->config->getUserValue($this->userId, Application::APP_ID, 'zoom');
		$lastPitch = $this->config->getUserValue($this->userId, Application::APP_ID, 'pitch');
		$lastBearing = $this->config->getUserValue($this->userId, Application::APP_ID, 'bearing');
		$lastMapStyle = $this->config->getUserValue($this->userId, Application::APP_ID, 'mapStyle');
		$lastTerrain = $this->config->getUserValue($this->userId, Application::APP_ID, 'terrain');
		$lastLinkType = $this->config->getUserValue($this->userId, Application::APP_ID, 'linkType');
		if ($lastLat !== '' && $lastLon !== '' && $lastZoom !== ''
			&& $lastPitch !== '' && $lastBearing !== '' && $lastMapStyle !== '') {
			$this->initialState->provideInitialState('last-map-state', [
				'lat' => (float) $lastLat,
				'lon' => (float) $lastLon,
				'zoom' => (int) $lastZoom,
				'pitch' => (float) $lastPitch,
				'bearing' => (float) $lastBearing,
				'mapStyle' => $lastMapStyle,
				'terrain' => $lastTerrain,
				'linkType' => $lastLinkType,
			]);
		}

		Util::addScript(Application::APP_ID, Application::APP_ID . '-referenceLocation');
	}
}
