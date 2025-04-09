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

namespace OCA\Osm\Reference;

use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\OsmAPIService;
use OCA\Osm\Service\UtilsService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\LinkReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;

use OCP\IURLGenerator;

class OsmPointReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_location';

	public function __construct(
		private OsmAPIService $osmAPIService,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private UtilsService $utilsService,
		private ?string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'openstreetmap-point';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Map location (by OpenStreetMap)');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		return ['openstreetmap-search-location'];
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		$adminLinkPreviewEnabled = $this->appConfig->getValueString(Application::APP_ID, 'link_preview_enabled', '1') === '1';
		$userLinkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
		if (!$adminLinkPreviewEnabled || !$userLinkPreviewEnabled) {
			return false;
		}

		return $this->getCoordinates($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$coords = $this->getCoordinates($referenceText);
			if (isset($coords['markerLat'], $coords['markerLon'])) {
				$pointInfo = $this->osmAPIService->geocode($this->userId, $coords['markerLat'], $coords['markerLon']);
			} else {
				// do not geocode if no marker, the widget will simply show the map centered correctly
				//				$pointInfo = $this->osmAPIService->geocode($this->userId, $coords['lat'], $coords['lon']);
				$pointInfo = [];
			}
			if (!isset($pointInfo['error'])) {
				$pointInfo['url'] = $referenceText;
				$reference = new Reference($referenceText);
				$geoLink = 'geo:' . $coords['lat'] . ':' . $coords['lon'] . '?z=' . $coords['zoom'];
				$reference->setTitle($pointInfo['display_name'] ?? $geoLink);
				if (isset($pointInfo['osm_type'], $pointInfo['osm_id'])) {
					$reference->setDescription($pointInfo['osm_type'] . '/' . $pointInfo['osm_id'] . ' ' . $geoLink);
					$reference->setUrl($this->osmAPIService->getLinkFromOsmId($pointInfo['osm_id'], $pointInfo['osm_type']));
				} else {
					$reference->setDescription($geoLink);
					$reference->setUrl($referenceText);
				}
				$logoUrl = $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'logo.svg')
				);
				$reference->setImageUrl($logoUrl);

				$pointInfo['zoom'] = $coords['zoom'] ?? 15;
				$pointInfo['pitch'] = $coords['pitch'] ?? null;
				$pointInfo['bearing'] = $coords['bearing'] ?? null;
				$pointInfo['style'] = $coords['style'] ?? null;
				if (isset($coords['terrain'])) {
					$pointInfo['terrain'] = $coords['terrain'];
				}
				$pointInfo['map_center'] = [
					'lat' => $coords['lat'],
					'lon' => $coords['lon'],
				];
				if (isset($coords['markerLat'], $coords['markerLon'])) {
					$pointInfo['marker_coordinates'] = [
						'lat' => $coords['markerLat'],
						'lon' => $coords['markerLon'],
					];
				}

				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$pointInfo,
				);
				return $reference;
			}
			// fallback to opengraph
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
	private function getCoordinates(string $url): ?array {
		// link examples:
		// https://www.openstreetmap.org/#map=5/47.931/24.829
		// https://www.openstreetmap.org/?mlat=44.7240&mlon=4.6374#map=13/44.7240/4.6374
		// https://osm.org/go/0IpWx-
		// https://osmand.net/map#17/43.61599/3.87524
		// https://osmand.net/map?pin=43.6954,3.8754#7/43.6954/3.8754
		// https://osm.org/?mlat=52.51629&mlon=13.37755&zoom=12
		// https://omaps.app/IyqbLiFkiD
		// https://omaps.app/IyqbLiFkiD/Etang_de_Thau
		preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/#map=(\d+)\/(-?\d+\.\d+)\/(-?\d+.\d+)/i', $url, $matches);
		if (count($matches) > 3) {
			$result = [
				'zoom' => (int)$matches[1],
				'lat' => (float)$matches[2],
				'lon' => (float)$matches[3],
			];
			return $this->getFragmentInfo($url, $result);
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/\?mlat=(-?\d+\.\d+)&mlon=(-?\d+\.\d+)#map=(\d+)\/(-?\d+\.\d+)\/(-?\d+.\d+)/i', $url, $matches);
		if (count($matches) > 5) {
			$result = [
				'zoom' => (int)$matches[3],
				'lat' => (float)$matches[4],
				'lon' => (float)$matches[5],
				'markerLat' => (float)$matches[1],
				'markerLon' => (float)$matches[2],
			];
			return $this->getFragmentInfo($url, $result);
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?omaps\.app\/([0-9a-zA-Z]+)(\/[^\/]+)?$/i', $url, $matches);
		if (count($matches) > 1) {
			$encodedCoords = $matches[1];
			$result = $this->utilsService->decodeOrganicMapsShortLink($encodedCoords);
			$result['markerLat'] = $result['lat'];
			$result['markerLon'] = $result['lon'];
			return $result;
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osm\.org\/go\/([0-9a-zA-Z\-\~]+)$/i', $url, $matches);
		if (count($matches) > 1) {
			$encodedCoords = $matches[1];
			return $this->utilsService->decodeOsmShortLink($encodedCoords);
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osmand\.net\/map\/?\?pin=(-?\d+\.\d+),(-?\d+\.\d+)#(\d+)\/(-?\d+\.\d+)\/(-?\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 5) {
			$result = [
				'zoom' => (int)$matches[3],
				'lat' => (float)$matches[4],
				'lon' => (float)$matches[5],
				'markerLat' => (float)$matches[1],
				'markerLon' => (float)$matches[2],
			];
			return $this->getFragmentInfo($url, $result);
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osmand\.net\/map\/?#(\d+)\/(-?\d+\.\d+)\/(-?\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 3) {
			$result = [
				'zoom' => (int)$matches[1],
				'lat' => (float)$matches[2],
				'lon' => (float)$matches[3],
			];
			return $this->getFragmentInfo($url, $result);
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osm\.org\/\?mlat=(-?\d+\.\d+)&mlon=(-?\d+\.\d+)&zoom=(\d+)$/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'zoom' => (int)$matches[3],
				'lat' => (float)$matches[1],
				'lon' => (float)$matches[2],
				'markerLat' => (float)$matches[1],
				'markerLon' => (float)$matches[2],
			];
		}

		return null;
	}

	/**
	 * @param string $url
	 * @param array $urlInfo
	 * @return array
	 */
	public static function getFragmentInfo(string $url, array $urlInfo): array {
		$fragment = parse_url($url, PHP_URL_FRAGMENT);
		parse_str($fragment, $params);
		if (isset($params['pitch'])) {
			$urlInfo['pitch'] = (int)$params['pitch'];
		}
		if (isset($params['bearing'])) {
			$urlInfo['bearing'] = (int)$params['bearing'];
		}
		if (isset($params['style'])) {
			$urlInfo['style'] = $params['style'];
		}
		if (isset($params['terrain'])) {
			$urlInfo['terrain'] = true;
		}
		return $urlInfo;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the GitHub account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
