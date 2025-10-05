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
use OCA\Osm\Service\RoutingService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Collaboration\Reference\LinkReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IAppConfig;
use OCP\IConfig;

use OCP\IL10N;
use OCP\IURLGenerator;

class OsmRouteReferenceProvider extends ADiscoverableReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_route';

	public function __construct(
		private RoutingService $routingService,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private IReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private ?string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'openstreetmap-direction';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Map directions (by OSRM)');
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
	public function matchReference(string $referenceText): bool {
		$adminLinkPreviewEnabled = $this->appConfig->getValueString(Application::APP_ID, 'link_preview_enabled', '1') === '1';
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
		$routing = $this->routingService->computeOsrmRoute($linkInfo['points'], $linkInfo['profile'], true, 'geojson');
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

		$routeInfo = [
			'queryPoints' => $linkInfo['points'],
			'type' => $linkInfo['type'] ?? 'osrm_org',
			'profile' => $linkInfo['profile'],
			'routes' => $routing['routes'],
			'waypoints' => $routing['waypoints'],
		];
		$routeInfo = OsmPointReferenceProvider::getFragmentInfo($referenceText, $routeInfo);

		$reference->setRichObject(
			self::RICH_OBJECT_TYPE,
			$routeInfo,
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
		// https://graphhopper.com/maps/?point=43.787469%2C3.736534&point=43.775434%2C3.867687&point=43.666524%2C3.861343_blabla&profile=foot&layer=Omniscale
		// https://routing.openstreetmap.de/?z=12&center=43.672590%2C3.864441&loc=43.720249%2C3.869934&loc=43.679046%2C3.939972&loc=43.611242%2C3.876734&hl=en&alt=0&srv=2
		// https://map.project-osrm.org/?z=9&center=50.572772%2C8.094177&loc=50.979182%2C7.910156&loc=50.162824%2C8.338623&hl=en&alt=0&srv=1
		// https://www.google.*/maps/dir/43.6577236,3.8765911/43.6637695,3.8992984/@43.6600832,3.8897232,14z/data=blabla?entry=ttu&g_ep=blabla
		// https://www.waze.com/*/live-map/directions?...=&to=ll.43.65818541%2C3.88821602&from=ll.43.64247306%2C3.85920525
		// https://osmand.net/map/navigate/?start=43.679527,3.928256&end=43.685827,3.928707&via=43.682615,3.926282;43.684105,3.930037&profile=pedestrian#16/43.6818/3.9262

		if (preg_match('/^(?:https?:\/\/)?(?:www\.)?osmand\.net\/map\/navigate\/\?.*start=(-?\d+\.\d+),(-?\d+\.\d+)/i', $url) === 1) {
			$query = parse_url($url, PHP_URL_QUERY);
			parse_str($query, $parsedQuery);
			if (isset($parsedQuery['start'], $parsedQuery['end'])) {
				$osmandProfiles = [
					'car' => 'car',
					'pedestrian' => 'foot',
					'bicycle' => 'bike',
				];
				$profile = 'car';
				if (isset($parsedQuery['profile']) && isset($osmandProfiles[$parsedQuery['profile']])) {
					$profile = $osmandProfiles[$parsedQuery['profile']];
				}
				preg_match('/^(-?\d+\.\d+),(-?\d+\.\d+)/i', $parsedQuery['start'], $fromMatches);
				preg_match('/^(-?\d+\.\d+),(-?\d+\.\d+)/i', $parsedQuery['end'], $toMatches);
				$formattedVias = [];
				if (isset($parsedQuery['via'])) {
					$vias = explode(';', $parsedQuery['via']);
					foreach ($vias as $via) {
						if (preg_match('/^(-?\d+\.\d+),(-?\d+\.\d+)/i', $via, $viaMatches) === 1) {
							$formattedVias[] = [(float)$viaMatches[1], (float)$viaMatches[2]];
						}
					}
				}
				return [
					'profile' => $profile,
					'points' => [
						[(float)$fromMatches[1], (float)$fromMatches[2]],
						...$formattedVias,
						[(float)$toMatches[1], (float)$toMatches[2]],
					],
				];
			}
		}

		if (preg_match('/^(?:https?:\/\/)?(?:www\.)?waze\.com\/[a-z-_]+\/live-map\/directions\?/i', $url) === 1) {
			$query = parse_url($url, PHP_URL_QUERY);
			parse_str($query, $parsedQuery);
			if (isset($parsedQuery['from'], $parsedQuery['to'])) {
				preg_match('/^ll\.(-?\d+\.\d+),(-?\d+\.\d+)/i', $parsedQuery['from'], $fromMatches);
				preg_match('/^ll\.(-?\d+\.\d+),(-?\d+\.\d+)/i', $parsedQuery['to'], $toMatches);
				return [
					'profile' => 'car',
					'points' => [
						[(float)$fromMatches[1], (float)$fromMatches[2]],
						[(float)$toMatches[1], (float)$toMatches[2]],
					],
				];
			}
		}

		$osrmBaseUrls = [
			'https://routing.openstreetmap.de',
			'https://map.project-osrm.org',
		];

		foreach ($osrmBaseUrls as $osrmBaseUrl) {
			if (preg_match('/^' . preg_quote($osrmBaseUrl, '/') . '\/\?.*loc=(-?\d+\.\d+)%2C(-?\d+\.\d+)/i', $url) === 1) {
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
						}, $parsedQuery['loc']),
						'type' => $osrmBaseUrl === 'https://routing.openstreetmap.de' ? 'osrm_osm_de' : 'osrm_org',
					];
				}
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
					}, $parsedQuery['point']),
					'type' => 'graphhopper_com',
				];
			}
		}

		if (preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/directions\?.*route=(-?\d+\.\d+)%2C(-?\d+\.\d+)%3B(-?\d+\.\d+)%2C(-?\d+\.\d+)/i', $url, $matches) === 1) {
			$query = parse_url($url, PHP_URL_QUERY);
			parse_str($query, $parsedQuery);
			if (isset($parsedQuery['engine']) && is_string($parsedQuery['engine'])) {
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
				$profile = $osmProfiles[$parsedQuery['engine']] ?? 'car';
			} else {
				$profile = 'car';
			}
			return [
				'profile' => $profile,
				'points' => [
					[(float)$matches[1], (float)$matches[2]],
					[(float)$matches[3], (float)$matches[4]],
				],
			];
		}

		if (preg_match('/^(?:https?:\/\/)?(?:www\.)?google\.[a-z]+\/maps\/dir\/(-?\d+\.\d+),(-?\d+\.\d+)\/(-?\d+\.\d+),(-?\d+\.\d+)\//i', $url, $matches) === 1) {
			$profile = 'car';
			if (preg_match('/data=[^?]+3e(\d)\?/i', $url, $profileMatches) === 1) {
				if ($profileMatches[1] === '1') {
					$profile = 'bike';
				} elseif ($profileMatches[1] === '2') {
					$profile = 'foot';
				}
			}
			return [
				'profile' => $profile,
				'points' => [
					[(float)$matches[1], (float)$matches[2]],
					[(float)$matches[3], (float)$matches[4]],
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
