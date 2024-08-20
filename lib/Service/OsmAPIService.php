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

class OsmAPIService {
	private IClient $client;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		IClientService $clientService
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $service
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string|null $s
	 * @return string|null
	 * @throws Exception
	 */
	public function getRasterTile(string $service, int $x, int $y, int $z, ?string $s = null): ?string {
		$options = [];
		if ($service === 'osm') {
			$url = 'https://tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
		} elseif ($service === 'osm-highres') {
			$url = 'https://tile.osmand.net/hd/' . $z . '/' . $x . '/' . $y . '.png';
		} elseif ($service === 'esri-topo') {
			$url = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/' . $z . '/' . $y . '/' . $x;
		} elseif ($service === 'watercolor') {
			$url = 'https://tiles.stadiamaps.com/styles/stamen_watercolor/' . $z . '/' . $x . '/' . $y . '.jpg';
			// see https://docs.stadiamaps.com/authentication
			$options['headers'] = ['Origin' => 'https://nextcloud.local'];
		} else {
			$url = 'https://tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
		}
		$body = $this->client->get($url, $options)->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			return $content === false
				? ''
				: $content;
		}
		return $body;
	}

	/**
	 * @param string $fontstack
	 * @param string $range
	 * @param string|null $key
	 * @return string|null
	 * @throws Exception
	 */
	public function getMapTilerFont(string $fontstack, string $range, ?string $key = null): ?string {
		// https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey
		$url = 'https://api.maptiler.com/fonts/' . $fontstack . '/' . $range . '.pbf';
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$body = $this->client->get($url)->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			return $content === false
				? null
				: $content;
		}
		return $body;
	}

	/**
	 * @param float $lat
	 * @param float $lon
	 * @param int $zoom
	 * @param bool $includeMarker
	 * @return string
	 */
	public function getLinkFromCoordinates(float $lat, float $lon, int $zoom = 12, bool $includeMarker = true): string {
		if ($includeMarker) {
			return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=' . $zoom . '/' . $lat . '/' . $lon;
		}
		return 'https://www.openstreetmap.org/#map=' . $zoom . '/' . $lat . '/' . $lon;
	}

	public function getLinkFromOsmId(int $osmId, string $osmType): string {
		return 'https://www.openstreetmap.org/'. urlencode($osmType) . '/' . $osmId;
	}

	/**
	 * @param string $userId
	 * @param int $locationId
	 * @param string $locationType
	 * @return array
	 */
	public function getLocationInfo(string $userId, int $locationId, string $locationType): ?array {
		// example:
		// curl https://nominatim.openstreetmap.org/lookup?osm_ids=R87515&format=json&addressdetails=1&extratags=1&namedetails=1&polygon_geojson=1
		$prefix = $locationType === 'relation'
			? 'R'
			: ($locationType === 'way'
				? 'W'
				: 'N');
		$params = [
			'osm_ids' => $prefix . $locationId,
			'format' => 'json',
			'addressdetails' => 1,
			'extratags' => 1,
			'namedetails' => 1,
			'polygon_geojson' => 1,
		];
		$result = $this->request($userId, 'lookup', $params);
		if (count($result) === 1) {
			return $result[0];
		}
		$this->logger->debug('Osm API error : no response for lookup ' .$locationType . '/' . $locationId, ['app' => Application::APP_ID]);
		return null;
	}

	/**
	 * Search items
	 *
	 * @param string $userId
	 * @param string $query
	 * @param string $format
	 * @param array $extraParams
	 * @param int $offset
	 * @param int $limit
	 * @return array request result
	 */
	public function searchLocation(string $userId, string $query, string $format, array $extraParams = [],
		int $offset = 0, int $limit = 5): array {
		// no pagination...
		$limitParam = $offset + $limit;
		$params = [
			'q' => $query,
			'format' => $format,
			'limit' => $limitParam,
		];
		foreach ($extraParams as $k => $v) {
			if ($v !== null) {
				$params[$k] = $v;
			}
		}
		$result = $this->request($userId, 'search', $params);
		if (!isset($result['error'])) {
			return array_slice($result, $offset, $limit);
		}
		return $result;
	}

	/**
	 * @param string $userId
	 * @param float $lat
	 * @param float $lon
	 * @param bool $includePolygon
	 * @return array
	 */
	public function geocode(string $userId, float $lat, float $lon, bool $includePolygon = true): array {
		// example:
		// curl https://nominatim.openstreetmap.org/reverse?format=json&lat=47.931&lon=24.829&addressdetails=1&polygon_geojson=1
		$params = [
			'format' => 'json',
			'lat' => $lat,
			'lon' => $lon,
			'addressdetails' => 1,
		];
		if ($includePolygon) {
			$params['polygon_geojson'] = 1;
		}
		return $this->request($userId, 'reverse', $params);
	}

	/**
	 * Make an HTTP request to the Osm API
	 * @param string|null $userId
	 * @param string $endPoint The path to reach in api.github.com
	 * @param array $params Query parameters (key/val pairs)
	 * @param string $method HTTP query method
	 * @param bool $rawResponse
	 * @return array decoded request result or error
	 */
	public function request(?string $userId, string $endPoint, array $params = [], string $method = 'GET', bool $rawResponse = false): array {
		try {
			$url = 'https://nominatim.openstreetmap.org/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud OpenStreetMap integration',
					//					'Authorization' => 'MediaBrowser Token="' . $token . '"',
					'Content-Type' => 'application/json',
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = json_encode($params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				if ($rawResponse) {
					return [
						'body' => $body,
						'headers' => $response->getHeaders(),
					];
				} else {
					return json_decode($body, true) ?: [];
				}
			}
		} catch (ClientException | ServerException $e) {
			$responseBody = $e->getResponse()->getBody();
			$parsedResponseBody = json_decode($responseBody, true);
			if ($e->getResponse()->getStatusCode() === 404) {
				// Only log inaccessible github links as debug
				$this->logger->debug('Osm API error : ' . $e->getMessage(), ['response_body' => $parsedResponseBody, 'app' => Application::APP_ID]);
			} else {
				$this->logger->warning('Osm API error : ' . $e->getMessage(), ['response_body' => $parsedResponseBody, 'app' => Application::APP_ID]);
			}
			return [
				'error' => $e->getMessage(),
				'body' => $parsedResponseBody,
			];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Osm API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
