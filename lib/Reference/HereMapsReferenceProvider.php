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
use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\OsmAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;

use OCP\IURLGenerator;

class HereMapsReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_location';

	public function __construct(
		private OsmAPIService $osmAPIService,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private ?string $userId
	) {
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

					$pointInfo['zoom'] = $coords['zoom'] ?? 14;
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
		// https://wego.here.com/france/privas/city-town-village/privas--here:cm:namedplace:20047816?map=44.73471,4.59783,14,normal&msg=Privas
		// https://wego.here.com/?map=44.73471,4.59783,14,normal
		// https://share.here.com/l/51.8772465,14.3453293?z=13&t=traffic&p=no
		// https://share.here.com/p/s-YmI9NC41NzAyJTJDNDQuNjk2OTQlMkM0LjYxOTY4JTJDNDQuNzQ1NTg7Yz1jaXR5LXRvd24tdmlsbGFnZTtpZD1oZXJlJTNBY20lM0FuYW1lZHBsYWNlJTNBMjAwNDc4MTY7bGF0PTQ0LjczNDcxO2xvbj00LjU5NzgzO249UHJpdmFzO2g9MmY2NTNm

		preg_match('/^(?:https?:\/\/)?(?:www\.)?wego\.here\.com\/.*\?map=([+-]?\d+\.\d+),([+-]?\d+\.\d+),(\d+)/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'lat' => (float) $matches[1],
				'lon' => (float) $matches[2],
				'zoom' => (int) $matches[3],
			];
		}

		// https://share.here.com/l/51.8772465,14.3453293?z=13&t=traffic&p=no
		preg_match('/^(?:https?:\/\/)?(?:www\.)?share\.here\.com\/l\/([+-]?\d+\.\d+),([+-]?\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 2) {
			$coords = [
				'lat' => (float) $matches[1],
				'lon' => (float) $matches[2],
			];
			preg_match('/z=(\d+)/i', $url, $matches);
			if (count($matches) > 1) {
				$coords['zoom'] = (int) $matches[1];
			}
			preg_match('/(p=yes)/i', $url, $matches);
			if (count($matches) > 1) {
				$coords['markerLat'] = $coords['lat'];
				$coords['markerLon'] = $coords['lon'];
			}
			return $coords;
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
