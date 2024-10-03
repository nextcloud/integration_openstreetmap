<?php
/**
 * Nextcloud - OpenStreetMap
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Osm\Controller;

use Exception;
use OCA\Osm\Service\OsmAPIService;
use OCA\Osm\Service\RoutingService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCSController;

use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

class OsmAPIController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private OsmAPIService $osmAPIService,
		private RoutingService $routingService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $q
	 * @param int $limit
	 * @param string $rformat
	 * @param int|null $polygon_geojson
	 * @param int|null $addressdetails
	 * @param int|null $namedetails
	 * @param int|null $extratags
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function nominatimSearch(
		string $q, int $limit = 10, string $rformat = 'json',
		?int $polygon_geojson = null, ?int $addressdetails = null, ?int $namedetails = null, ?int $extratags = null,
	): DataResponse {
		$extraParams = [
			'polygon_geojson' => $polygon_geojson,
			'addressdetails' => $addressdetails,
			'namedetails' => $namedetails,
			'extratags' => $extratags,
		];
		$searchResults = $this->osmAPIService->searchLocation($this->userId, $q, $rformat, $extraParams, 0, $limit);
		if (isset($searchResults['error'])) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$response = new DataResponse($searchResults);
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * @param string $service
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string|null $s
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getRasterTile(string $service, int $x, int $y, int $z, ?string $s = null): Response {
		try {
			$response = new DataDisplayResponse($this->osmAPIService->getRasterTile($service, $x, $y, $z, $s));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Raster tile not found', ['exception' => $e]);
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $fontstack
	 * @param string $range
	 * @param string|null $key
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerFont(string $fontstack, string $range, ?string $key = null): Response {
		try {
			$response = new DataDisplayResponse($this->osmAPIService->getMapTilerFont($fontstack, $range, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Font not found', ['exception' => $e]);
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}


	/**
	 * @param string $profile
	 * @param string $coordinates
	 * @param string|null $alternatives
	 * @param string|null $geometries
	 * @param string|null $steps
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getOsrmRoutes(
		string $profile, string $coordinates, ?string $alternatives = null,
		?string $geometries = null, ?string $steps = null,
	): JSONResponse {
		$alt = $alternatives === null
			? null
			: ($alternatives === 'true');
		$stp = $steps === null
			? null
			: ($steps === 'true');
		$rawRouteResponse = $this->routingService->getOsrmRoute($coordinates, $profile, $alt, $geometries, $stp);
		$arrayRoute = json_decode($rawRouteResponse, true);
		if (is_array($arrayRoute)) {
			$response = new JSONResponse($arrayRoute);
			$response->cacheFor(60 * 60 * 24, false, true);
		} else {
			$response = new JSONResponse(['error' => 'Result is not an array'], Http::STATUS_BAD_REQUEST);
		}
		return $response;
	}
}
