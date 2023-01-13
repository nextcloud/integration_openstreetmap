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
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use Throwable;

class OsmAPIService {
	private LoggerInterface $logger;
	private IL10N $l10n;
	private IConfig $config;
	private IURLGenerator $urlGenerator;
	private IClient $client;

	/**
	 * Service to make requests to Osm REST API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IClientService $clientService) {
		$this->client = $clientService->newClient();
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
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
			return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=11/' . $lat . '/' . $lon;
		}
		return 'https://www.openstreetmap.org/#map=11/' . $lat . '/' . $lon;
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
	 * @param int $offset
	 * @param int $limit
	 * @return array request result
	 */
	public function searchLocation(string $userId, string $query, int $offset = 0, int $limit = 5): array {
		// no pagination...
		$limitParam = $offset + $limit;
		$params = [
			'format' => 'json',
			'addressdetails' => 1,
			'extratags' => 1,
			'namedetails' => 1,
			'limit' => $limitParam,
		];
		$result = $this->request($userId, 'search/' . urlencode($query), $params);
		if (!isset($result['error'])) {
			return array_slice($result, $offset, $limit);
		}
		return $result;
	}

	/**
	 * @param string $userId
	 * @param float $lat
	 * @param float $lon
	 * @return array
	 */
	public function geocode(string $userId, float $lat, float $lon): array {
		$params = [
			'format' => 'json',
			'lat' => $lat,
			'lon' => $lon,
			'addressdetails' => 1,
		];
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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
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
