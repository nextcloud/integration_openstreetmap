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

use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Osm\Service\UtilsService;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\OsmAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;

use OCP\IURLGenerator;

class GoogleMapsReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_location';

	public function __construct(private OsmAPIService $osmAPIService,
								private IConfig $config,
								private IURLGenerator $urlGenerator,
								private ReferenceManager $referenceManager,
								private LinkReferenceProvider $linkReferenceProvider,
								private UtilsService $utilsService,
								private ?string $userId) {
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		$adminLinkPreviewEnabled = $this->config->getAppValue(Application::APP_ID, 'link_preview_enabled', '1') === '1';
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
			if ($coords !== null) {
				$pointInfo = $this->osmAPIService->geocode($this->userId, $coords['lat'], $coords['lon'], false);
				if ($pointInfo !== null && !isset($pointInfo['error'])) {
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

					$pointInfo['zoom'] = $coords['zoom'] ?? 14;
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
		// supported link examples:
		// https://maps.google.com/maps?q=24.197611,120.780512&ll=24.197611,121.780512&z=5
		// https://goo.gl/maps/eTvH3TqXvKhU8sqb8
		// https://maps.app.goo.gl/dJywohGrFXeBhtGd7
		// https://www.google.com/maps/place/44%C2%B044'46.5%22N+4%C2%B033'36.9%22E/@44.746241,4.560248,17z/data=!3m1!4b1!4m4!3m3!8m2!3d44.746241!4d4.560248
		// https://www.google.com/maps/search/44.746241,+4.560248?shorturl=1

		// https://maps.app.goo.gl/dJywohGrFXeBhtGd7
		preg_match('/^(?:https?:\/\/)?(?:www\.)?maps\.app\.goo\.gl\/([0-9a-zA-Z]+)$/i', $url, $matches);
		if (count($matches) > 1) {
			$url = $this->utilsService->decodeGoogleMapsAppShortLink($matches[1]);
		}

		// https://maps.google.com/maps?q=24.197611,120.780512&ll=24.197611,121.780512&z=5
		preg_match('/^(?:https?:\/\/)?(?:www\.)?maps\.google\.[a-z]+\/maps\?/i', $url, $matches);
		if (count($matches) > 0) {
			$result = [];
			$query = parse_url($url, PHP_URL_QUERY);
			parse_str($query, $params);

			if (isset($params['z'])) {
				$result['zoom'] = (int) $params['z'];
			}
			if (isset($params['ll'])) {
				$coords = explode(',', $params['ll']);
				if (count($coords) >= 2 && is_numeric($coords[0]) && is_numeric($coords[1])) {
					$result['lat'] = (float) $coords[0];
					$result['lon'] = (float) $coords[1];
				}
			}
			if (isset($params['q'])) {
				$coords = explode(',', $params['q']);
				if (count($coords) >= 2 && is_numeric($coords[0]) && is_numeric($coords[1])) {
					// center map on marker only if no map center provided as 'll' link query param
					if (!isset($result['lat'], $result['lon'])) {
						$result['lat'] = (float) $coords[0];
						$result['lon'] = (float) $coords[1];
					}
					$result['markerLat'] = (float) $coords[0];
					$result['markerLon'] = (float) $coords[1];
				}
			}
			// one of 'q' and 'll' must be set
			if (isset($params['q']) || isset($params['ll'])) {
				return OsmPointReferenceProvider::getFragmentInfo($url, $result);
			}
			return null;
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?goo\.gl\/maps\/([0-9a-zA-Z]+)$/i', $url, $matches);
		if (count($matches) > 1) {
			// we should get redirected to a supported URL
			$url = $this->utilsService->decodeGoogleMapsShortLink($matches[1]);
			if ($url !== null) {
				// example
				// https://maps.google.com/maps/api/staticmap?center=44.746241%2C4.560248&zoom=15&size=200x200&markers=44.746241%2C4.560248&sensor=false&client=google-maps-frontend&signature=c-Efll2S5GIhQbsP2KiHp-R82Js'
				preg_match('/^(?:https?:\/\/)?(?:www\.)?maps\.google\.com\/maps\/api\/staticmap\?center=([+-]?\d+\.\d+)%2C([+-]?\d+\.\d+)&zoom=(\d+).*&markers=([+-]?\d+\.\d+)%2C([+-]?\d+\.\d+)/i', $url, $matches);
				if (count($matches) > 5) {
					return [
						'lat' => (float) $matches[1],
						'lon' => (float) $matches[2],
						'zoom' => (int) $matches[3],
						'markerLat' => (float) $matches[4],
						'markerLon' => (float) $matches[5],
					];
				}
				preg_match('/^(?:https?:\/\/)?(?:www\.)?maps\.google\.com\/maps\/api\/staticmap\?center=([+-]?\d+\.\d+)%2C([+-]?\d+\.\d+)&zoom=(\d+)/i', $url, $matches);
				if (count($matches) > 3) {
					return [
						'zoom' => (int) $matches[3],
						'lat' => (float) $matches[1],
						'lon' => (float) $matches[2],
					];
				}
			}
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?google\.[a-z]+\/maps\/place\/.*\/@([+-]?\d+\.\d+),([+-]?\d+\.\d+),(\d+(?:\.\d+)?)z/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'lat' => (float) $matches[1],
				'lon' => (float) $matches[2],
				'zoom' => (int) $matches[3],
				'markerLat' => (float) $matches[1],
				'markerLon' => (float) $matches[2],
			];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?google\.[a-z]+\/maps\/search\/([+-]?\d+\.\d+),([+-]?\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 2) {
			return [
				'lat' => (float) $matches[1],
				'lon' => (float) $matches[2],
				'markerLat' => (float) $matches[1],
				'markerLon' => (float) $matches[2],
			];
		}

		return null;
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
