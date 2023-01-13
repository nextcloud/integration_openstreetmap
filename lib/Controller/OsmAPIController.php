<?php
/**
 * Nextcloud - OpenStreetMap
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Osm\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

use OCA\Osm\Service\OsmAPIService;
use OCP\IURLGenerator;

class OsmAPIController extends OCSController {

	private OsmAPIService $osmAPIService;
	private IURLGenerator $urlGenerator;
	private ?string $userId;

	public function __construct(string          $appName,
								IRequest        $request,
								OsmAPIService   $osmAPIService,
								IURLGenerator   $urlGenerator,
								?string         $userId) {
		parent::__construct($appName, $request);
		$this->osmAPIService = $osmAPIService;
		$this->urlGenerator = $urlGenerator;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $itemId
	 * @param string $fallbackName
	 * @param int $fillHeight
	 * @param int $fillWidth
	 * @param int $quality
	 * @return DataDownloadResponse|RedirectResponse
	 */
	public function getMediaImage(string $itemId, string $fallbackName, int $fillHeight = 44, int $fillWidth = 44, int $quality = 96) {
		$result = $this->osmAPIService->getMediaImage($this->userId, $itemId, $fillHeight, $fillWidth, $quality);
		if (isset($result['error'])) {
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $fallbackName, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		} else {
			$response = new DataDownloadResponse(
				$result['body'],
				'',
				$result['headers']['Content-Type'][0] ?? 'image/jpeg'
			);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		}
	}

	/**
	 * Redirects to the item's download link
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $itemId
	 * @return DataResponse|RedirectResponse
	 */
	public function internalMediaLink(string $itemId) {
		$downloadLink = $this->osmAPIService->getDownloadLink($itemId);
		if ($downloadLink === null) {
			return new DataResponse('', Http::STATUS_FORBIDDEN);
		}
		return new RedirectResponse($downloadLink);
	}
}
