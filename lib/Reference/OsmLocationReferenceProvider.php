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

class OsmLocationReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_location';

	public function __construct(
		private OsmAPIService $osmAPIService,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private ReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private UtilsService $utilsService,
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

		return $this->getCoordinates($referenceText) !== null || $this->getLocationTypeId($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$coords = $this->getCoordinates($referenceText);
			$locationTypeId = $this->getLocationTypeId($referenceText);
			$locationInfo = $this->osmAPIService->getLocationInfo($this->userId, $locationTypeId['id'], $locationTypeId['type']);
			if ($locationInfo !== null) {
				$locationInfo['url'] = $referenceText;
				$reference = new Reference($referenceText);
				$geoLink = $coords === null
					? ''
					: ' geo:' . $coords['lat'] . ':' . $coords['lon'] . '?z=' . $coords['zoom'];
				$reference->setTitle($locationInfo['display_name']);
				$reference->setDescription($locationTypeId['type'] . '/' . $locationTypeId['id'] . $geoLink);
				$logoUrl = $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'logo.svg')
				);
				$reference->setImageUrl($logoUrl);

				$locationInfo = OsmPointReferenceProvider::getFragmentInfo($referenceText, $locationInfo);

				if ($coords !== null) {
					if (isset($coords['lat'], $coords['lon'])) {
						$locationInfo['map_center'] = [
							'lat' => $coords['lat'],
							'lon' => $coords['lon'],
						];
					}
					if (isset($coords['zoom'])) {
						$locationInfo['zoom'] = $coords['zoom'];
					}
				}
				$locationInfo['marker_coordinates'] = [
					'lat' => $locationInfo['lat'],
					'lon' => $locationInfo['lon'],
				];
				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$locationInfo,
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
		// supported link examples:
		// https://www.openstreetmap.org/relation/87515#map=14/44.7209/4.5877
		// https://www.openstreetmap.org/relation/87515
		// https://osm.org/go/xV9hTJVw-?relation=87515

		preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/[a-zA-Z]+\/\d+#map=(\d+)\/(-?\d+\.\d+)\/(-?\d+\.\d+)$/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'zoom' => (int) $matches[1],
				'lat' => (float) $matches[2],
				'lon' => (float) $matches[3],
			];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osm\.org\/go\/([0-9a-zA-Z\-]+)\?[a-zA-Z]+=\d+$/i', $url, $matches);
		if (count($matches) > 1) {
			$encodedCoords = $matches[1];
			return $this->utilsService->decodeOsmShortLink($encodedCoords);
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
	private function getLocationTypeId(string $url): ?array {
		preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/([a-zA-Z]+)\/(\d+)/i', $url, $matches);
		if (count($matches) > 2) {
			return [
				'type' => $matches[1],
				'id' => $matches[2],
			];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osm\.org\/go\/[0-9a-zA-Z\-]+\?([a-zA-Z]+)=(\d+)$/i', $url, $matches);
		if (count($matches) > 2) {
			return [
				'type' => $matches[1],
				'id' => $matches[2],
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
