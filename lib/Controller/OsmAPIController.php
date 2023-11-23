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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

use OCA\Osm\Service\OsmAPIService;

class OsmAPIController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private OsmAPIService $osmAPIService,
		private ?string $userId
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
		?int $polygon_geojson = null, ?int $addressdetails = null, ?int $namedetails = null, ?int $extratags = null
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
}
