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
use OCA\Osm\Service\MaptilerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;

use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

class MaptilerController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private MaptilerService $mapService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerStyle(string $version, ?string $key = null): Response {
		try {
			$response = new JSONResponse($this->mapService->getMapTilerStyle($version, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Style not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
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
			$response = new DataDisplayResponse($this->mapService->getMapTilerFont($fontstack, $range, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Font not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerTiles(string $version, ?string $key = null): JSONResponse {
		try {
			$response = new JSONResponse($this->mapService->getMapTilerTiles($version, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Tiles not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param array $headers
	 * @param string $defaultType
	 * @return string
	 */
	private function getContentTypeFromHeaders(array $headers, string $defaultType): string {
		if (isset($headers['Content-Type'])) {
			if (is_string($headers['Content-Type'])) {
				return $headers['Content-Type'];
			} elseif (is_array($headers['Content-Type'])
				&& count($headers['Content-Type']) > 0
				&& is_string($headers['Content-Type'][0])
			) {
				return $headers['Content-Type'][0];
			}
		}
		return $defaultType;
	}

	/**
	 * @param string $version
	 * @param int $z
	 * @param int $x
	 * @param int $y
	 * @param string $ext
	 * @param string|null $key
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerTile(string $version, int $z, int $x, int $y, string $ext, ?string $key = null): Response {
		try {
			$tileResponse = $this->mapService->getMapTilerTile($version, $x, $y, $z, $ext, $key);
			$response = new DataDisplayResponse(
				$tileResponse['body'],
				Http::STATUS_OK,
				['Content-Type' => $this->getContentTypeFromHeaders($tileResponse['headers'], 'image/jpeg')]
			);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Tile not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $version
	 * @param string $ext
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerSprite(string $version, string $ext): Response {
		try {
			if ($ext === 'json') {
				$sprite = $this->mapService->getMapTilerSpriteJson($version);
				$response = new JSONResponse($sprite);
			} else {
				$sprite = $this->mapService->getMapTilerSpriteImage($version, $ext);
				$response = new DataDisplayResponse(
					$sprite['body'],
					Http::STATUS_OK,
					['Content-Type' => $this->getContentTypeFromHeaders($sprite['headers'], 'image/png')]
				);
			}
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Sprite not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $name
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerResource(string $name): Response {
		try {
			$resourceResponse = $this->mapService->getMapTilerResource($name);
			$response = new DataDisplayResponse(
				$resourceResponse['body'],
				Http::STATUS_OK,
				['Content-Type' => $this->getContentTypeFromHeaders($resourceResponse['headers'], 'image/png')]
			);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Resource not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}
}
