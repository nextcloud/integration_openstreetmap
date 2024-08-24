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

use OCA\Osm\Service\RoutingService;
use OCP\Collaboration\Reference\LinkReferenceProvider;
use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\UtilsService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;

use OCP\IURLGenerator;

class OsmRouteReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_route';

	public function __construct(
		private RoutingService $routingService,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IReferenceManager $referenceManager,
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

		return $this->getLinkInfo($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if (!$this->matchReference($referenceText)) {
			return null;
		}
		$linkInfo = $this->getLinkInfo($referenceText);
		if ($linkInfo === null) {
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}
		$routing = $this->routingService->computeOsrmRoute($linkInfo['points'], $linkInfo['profile']);
		if ($routing === null) {
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}

		$reference = new Reference($referenceText);
		$reference->setTitle('Route');
		$reference->setDescription($linkInfo['profile']);
		$logoUrl = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'logo.svg')
		);
		$reference->setImageUrl($logoUrl);

		$reference->setRichObject(
			self::RICH_OBJECT_TYPE,
			[
				'queryPoints' => $linkInfo['points'],
				'profile' => $linkInfo['profile'],
				...$routing,
			],
		);
		return $reference;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
	private function getLinkInfo(string $url): ?array {
		// supported link examples:
		// https://www.openstreetmap.org/directions?engine=fossgis_osrm_bike&route=43.69788%2C3.86245%3B43.66652%2C3.86134
		// https://graphhopper.com/maps/?point=43.787469%2C3.736534
		// &point=43.775434%2C3.867687
		// &point=43.666524%2C3.861343_Montferrier-sur-Lez%2C+34980%2C+Occitanie%2C+France
		// &profile=foot&layer=Omniscale
		// https://routing.openstreetmap.de/?z=12&center=43.672590%2C3.864441&loc=43.720249%2C3.869934&loc=43.679046%2C3.939972&loc=43.611242%2C3.876734&hl=en&alt=0&srv=2

		if (preg_match('/^(?:https?:\/\/)?(?:www\.)?routing\.openstreetmap\.de\/\?.*loc=(-?\d+\.\d+)%2C(-?\d+\.\d+)/i', $url) === 1) {
			$fixedUrl = str_replace('loc=', 'loc[]=', $url);
			$query = parse_url($fixedUrl, PHP_URL_QUERY);
			parse_str($query, $parsedQuery);
			if (isset($parsedQuery['loc']) && is_array($parsedQuery['loc']) && count($parsedQuery['loc']) >= 2) {
				$osrmProfiles = [
					'0' => 'car',
					'1' => 'bike',
					'2' => 'foot',
				];
				return [
					'profile' => $osrmProfiles[$parsedQuery['srv'] ?? '0'] ?? 'car',
					'points' => array_map(function ($point) {
						preg_match('/^(-?\d+\.\d+),(-?\d+\.\d+)/i', $point, $matches);
						if (count($matches) > 2) {
							return [(float)$matches[1], (float)$matches[2]];
						}
						return null;
					}, $parsedQuery['loc'])
				];
			}
		}

		if (preg_match('/^(?:https?:\/\/)?(?:www\.)?graphhopper\.com\/maps\/\?.*point=(-?\d+\.\d+)%2C(-?\d+\.\d+)/i', $url) === 1) {
			$fixedUrl = str_replace('point=', 'point[]=', $url);
			$query = parse_url($fixedUrl, PHP_URL_QUERY);
			parse_str($query, $parsedQuery);
			if (isset($parsedQuery['point']) && is_array($parsedQuery['point']) && count($parsedQuery['point']) >= 2) {
				$ghpProfiles = [
					'car' => 'car',
					'bike' => 'bike',
					'foot' => 'foot',
				];
				return [
					'profile' => $ghpProfiles[$parsedQuery['profile']] ?? 'car',
					'points' => array_map(function ($point) {
						preg_match('/^(-?\d+\.\d+),(-?\d+\.\d+)/i', $point, $matches);
						if (count($matches) > 2) {
							return [(float)$matches[1], (float)$matches[2]];
						}
						return null;
					}, $parsedQuery['point'])
				];
			}
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/directions\?engine=([a-z_]+)&route=(-?\d+\.\d+)%2C(-?\d+\.\d+)%3B(-?\d+\.\d+)%2C(-?\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 5) {
			$osmProfiles = [
				'fossgis_osrm_car' => 'car',
				'fossgis_osrm_bike' => 'bike',
				'fossgis_osrm_foot' => 'foot',
				'graphhopper_car' => 'car',
				'graphhopper_bicycle' => 'bike',
				'graphhopper_foot' => 'foot',
				'fossgis_valhalla_car' => 'car',
				'fossgis_valhalla_bicycle' => 'bike',
				'fossgis_valhalla_foot' => 'foot',
			];
			return [
				'profile' => $osmProfiles[$matches[1]] ?? 'car',
				'points' => [
					[(float)$matches[2], (float)$matches[3]],
					[(float)$matches[4], (float)$matches[5]],
				],
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
