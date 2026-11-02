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
use OCA\Osm\Service\OpenfreemapService;
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

class MapController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private MaptilerService $maptilerService,
		private OpenfreemapService $openfreemapService,
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
			$response = new JSONResponse($this->maptilerService->getMapTilerStyle($version, $key));
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
			$response = new DataDisplayResponse($this->maptilerService->getMapTilerFont($fontstack, $range, $key));
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
			$response = new JSONResponse($this->maptilerService->getMapTilerTiles($version, $key));
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
		$header = $headers['Content-Type'] ?? $headers['content-type'] ?? null;
		if ($header !== null) {
			if (is_string($header)) {
				return $header;
			} elseif (is_array($header)
				&& count($header) > 0
				&& is_string($header[0])
			) {
				return $header[0];
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
			$tileResponse = $this->maptilerService->getMapTilerTile($version, $x, $y, $z, $ext, $key);
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
	 * @param int $z
	 * @param int $x
	 * @param int $y
	 * @param string|null $key
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerTileNoExt(string $version, int $z, int $x, int $y, ?string $key = null): Response {
		return $this->getMapTilerTile($version, $z, $x, $y, '', $key);
	}

	/**
	 * @param string $version
	 * @param string $ext
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerSpriteNoSize(string $version, string $ext): Response {
		return $this->getMapTilerSprite($version, $ext);
	}

	/**
	 * @param string $version
	 * @param string $ext
	 * @param string $size
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerSprite(string $version, string $ext, string $size = ''): Response {
		try {
			if ($ext === 'json') {
				$sprite = $this->maptilerService->getMapTilerSpriteJson($version, $size);
				$response = new JSONResponse($sprite);
			} else {
				$sprite = $this->maptilerService->getMapTilerSpriteImage($version, $size, $ext);
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
			$resourceResponse = $this->maptilerService->getMapTilerResource($name);
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

	/**
	 * @param string $style
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getOpenFreeMapStyle(string $style): Response {
		try {
			$response = new JSONResponse($this->openfreemapService->getOpenFreeMapStyle($style));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('OpenFreeMap style not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Proxy fonts, sprites, tiles.json and tiles of OpenFreeMap
	 *
	 * @param string $path
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getOpenFreeMapResource(string $path): Response {
		try {
			$resource = $this->openfreemapService->getOpenFreeMapResource($path);
			$response = new DataDisplayResponse($resource['body'], headers: [
				'Content-Type' => $resource['content-type'],
			]);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('OpenFreeMap resource not found', ['exception' => $e]);
			return new DataDisplayResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}
}
