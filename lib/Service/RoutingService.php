<?php
/**
 * Nextcloud - Osm
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Osm\Service;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class RoutingService {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function getOsrmRoute(
		string $coordinates, ?string $profile = null, ?bool $alternatives = null,
		?string $geometries = null, ?bool $steps = null, ?string $overview = null,
	): string {
		// OSRM URL example:
		// https://routing.openstreetmap.de/routed-bike/route/v1/driving/3.862452,43.6978777;3.8613428,43.6665244?overview=false&geometries=geojson&steps=true
		// profiles can be: routed-bike, routed-foot, routed-car

		$url = 'https://routing.openstreetmap.de/' . $profile . '/route/v1/driving/' . $coordinates;
		$url .= '?overview=' . ($overview ?? 'simplified');
		if ($geometries !== null) {
			$url .= '&geometries=' . $geometries;
		}
		if ($steps !== null) {
			$url .= '&steps=' . ($steps ? 'true' : 'false');
		}
		if ($alternatives !== null) {
			$url .= '&alternatives=' . ($alternatives ? 'true' : 'false');
		}

		$options = [];
		return (string)$this->client->get($url, $options)->getBody();
	}

	public function computeOsrmRoute(
		array $points, ?string $profile = null, bool $alternatives = true, ?string $geometries = null, bool $steps = true,
	): ?array {
		$pointsPath = implode(
			';',
			array_map(function (array $point) {
				return $point[1] . ',' . $point[0];
			}, $points)
		);
		$body = $this->getOsrmRoute($pointsPath, 'routed-' . $profile, $alternatives, $geometries, $steps);
		$bodyArray = json_decode($body, true);

		if (!isset($bodyArray['routes']) || empty($bodyArray['routes'])) {
			return null;
		}
		$routes = array_map(static function ($route) {
			$geojson = [
				'type' => 'FeatureCollection',
				'features' => [],
			];
			// legs
			if (!isset($route['legs']) || empty($route['legs'])) {
				return null;
			}
			foreach ($route['legs'] as $leg) {
				if (!isset($leg['steps']) || empty($leg['steps'])) {
					return null;
				}
				$steps = array_filter($leg['steps'], static function ($step) {
					return ($step['geometry']['type'] ?? '') === 'LineString';
				});
				// one feature per step
				/*
				array_push(
					$geojson['features'],
					...array_map(static function ($step) {
						return [
							'type' => 'Feature',
							'geometry' => $step['geometry'],
							'properties' => ['color' => 'red'],
						];
					}, $steps)
				);
				*/
				// one single feature per leg, all steps together
				$geojson['features'][] = [
					'type' => 'Feature',
					'geometry' => [
						'type' => 'LineString',
						'coordinates' => array_reduce(
							$steps,
							static function ($carry, $step) {
								return array_merge($carry, $step['geometry']['coordinates']);
							},
							[]
						),
					],
					'properties' => ['color' => ''],
				];
			}
			return [
				'duration' => $route['duration'],
				'distance' => $route['distance'],
				'geojson' => $geojson,
			];
		}, $bodyArray['routes']);

		// waypoints
		$waypoints = array_map(static function (array $waypoint) {
			return [
				'name' => $waypoint['name'],
				'location' => $waypoint['location'],
			];
		}, $bodyArray['waypoints'] ?? []);
		if (count($waypoints) >= 2) {
			$waypoints[0]['color'] = 'lightgreen';
			$waypoints[count($waypoints) - 1]['color'] = 'red';
		}
		return [
			'waypoints' => $waypoints,
			'routes' => $routes,
		];
	}
}
