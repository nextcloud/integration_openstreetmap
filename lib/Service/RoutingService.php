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

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Osm\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Throwable;

class RoutingService {
	private IClient $client;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		IClientService $clientService
	) {
		$this->client = $clientService->newClient();
	}

	public function computeOsrmRoute(array $points, ?string $profile = null): ?array {
		// OSRM URL example:
		// https://routing.openstreetmap.de/routed-bike/route/v1/driving/3.862452,43.6978777;3.8613428,43.6665244?overview=false&geometries=geojson&steps=true
		// profiles can be: routed-bike, routed-foot, routed-car

		$url = 'https://routing.openstreetmap.de/routed-' . $profile . '/route/v1/driving/'
			. $points[0][1] . ',' . $points[0][0] . ';' . $points[1][1] . ',' . $points[1][0]
			. '?overview=false&geometries=geojson&steps=true';

		$options = [];
		$body = $this->client->get($url, $options)->getBody();
		$bodyArray = json_decode($body, true);

		if (!isset($bodyArray['routes']) || empty($bodyArray['routes'])) {
			return null;
		}
		$route = $bodyArray['routes'][0];
		$geojson = [
			'type' => 'FeatureCollection',
			'features' => [],
		];
		if (!isset($route['legs']) || empty($route['legs'])) {
			return null;
		}
		$leg = $route['legs'][0];
		if (!isset($leg['steps']) || empty($leg['steps'])) {
			return null;
		}
		$steps = array_filter($leg['steps'], static function ($step) {
				return ($step['geometry']['type'] ?? '') === 'LineString';
		});
		$geojson['features'] = array_map(static function ($step) {
			return [
				'type' => 'Feature',
				'geometry' => $step['geometry'],
				'properties' => ['color' => 'red'],
			];
		}, $steps);
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
			'duration' => $route['duration'],
			'distance' => $route['distance'],
			'geojson' => $geojson,
		];
	}
}
