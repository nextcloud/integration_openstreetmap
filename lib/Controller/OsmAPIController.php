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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

use OCA\Osm\Service\OsmAPIService;

class OsmAPIController extends OCSController {

	public function __construct(string          $appName,
								IRequest        $request,
								private OsmAPIService   $osmAPIService,
								private ?string         $userId) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $query
	 * @return DataResponse
	 */
	public function nominatimSearch(string $query): DataResponse {
		$searchResults = $this->osmAPIService->searchLocation($this->userId, $query, 0, 10);
		if (isset($searchResults['error'])) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$response = new DataResponse($searchResults);
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}
}
