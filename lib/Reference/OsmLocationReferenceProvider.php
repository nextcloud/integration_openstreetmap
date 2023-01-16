<?php
/**
 * @copyright Copyright (c) 2023 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Osm\Reference;

use OC\Collaboration\Reference\LinkReferenceProvider;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\OsmAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IL10N;

use OCP\IURLGenerator;

class OsmLocationReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_location';

	private OsmAPIService $osmAPIService;
	private ?string $userId;
	private IConfig $config;
	private ReferenceManager $referenceManager;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private LinkReferenceProvider $linkReferenceProvider;

	public function __construct(OsmAPIService $osmAPIService,
								IConfig $config,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								ReferenceManager $referenceManager,
								LinkReferenceProvider $linkReferenceProvider,
								?string $userId) {
		$this->osmAPIService = $osmAPIService;
		$this->userId = $userId;
		$this->config = $config;
		$this->referenceManager = $referenceManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string	{
		return 'openstreetmap-location';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('OpenStreetMap location');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int	{
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		if ($this->userId !== null) {
			$ids = [];
			$searchItemsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_location_enabled', '1') === '1';
			if ($searchItemsEnabled) {
				$ids[] = 'openstreetmap-search-location';
			}
			return $ids;
		}
		return ['openstreetmap-search-location'];
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		$adminLinkPreviewEnabled = $this->config->getAppValue(Application::APP_ID, 'link_preview_enabled', '1') === '1';
		$userLinkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
		if (!$adminLinkPreviewEnabled || !$userLinkPreviewEnabled) {
			return false;
		}

		// link examples:
		// https://www.openstreetmap.org/relation/87515#map=14/44.7209/4.5877
		// https://www.openstreetmap.org/relation/87515
		// https://osm.org/go/xV9hTJVw-?relation=87515
		return preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/[a-zA-Z]+\/\d+#map=\d+\/\d+\.\d+\/\d+\.\d+$/i', $referenceText) === 1
			|| preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/[a-zA-Z]+\/\d+$/i', $referenceText) === 1
			|| preg_match('/^(?:https?:\/\/)?(?:www\.)?osm\.org\/go\/[0-9a-zA-Z\-]+\?[a-zA-Z]+=\d+$/i', $referenceText) === 1;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$coords = $this->getCoordinates($referenceText);
			$locationTypeId = $this->getLocationTypeId($referenceText);
			$locationInfo = $this->osmAPIService->getLocationInfo($this->userId, $locationTypeId['id'], $locationTypeId['type']);
			if ($locationInfo !== null) {
				$locationInfo['url'] = $referenceText;
				$reference = new Reference($referenceText);
				$geoLink = $coords === null
					? ''
					: ' geo:' . $coords['lat'] . ':' . $coords['lon'] . '?z=' . $coords['zoom'];
				$reference->setTitle($locationInfo['display_name']);
				$reference->setDescription($locationTypeId['type'] . '/' . $locationTypeId['id'] . $geoLink);
				$logoUrl = $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'logo.svg')
				);
				$reference->setImageUrl($logoUrl);

				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$locationInfo,
				);
				return $reference;
			}
			// fallback to opengraph
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
	private function getCoordinates(string $url): ?array {
		preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/[a-zA-Z]+\/\d+#map=(\d+)\/(\d+\.\d+)\/(\d+\.\d+)$/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'zoom' => $matches[1],
				'lat' => $matches[2],
				'lon' => $matches[3],
			];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osm\.org\/go\/([0-9a-zA-Z\-]+)\?[a-zA-Z]+=\d+$/i', $url, $matches);
		if (count($matches) > 1) {
			$encodedCoords = $matches[1];
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
	private function getLocationTypeId(string $url): ?array {
		preg_match('/^(?:https?:\/\/)?(?:www\.)?openstreetmap\.org\/([a-zA-Z]+)\/(\d+)/i', $url, $matches);
		if (count($matches) > 2) {
			return [
				'type' => $matches[1],
				'id' => $matches[2],
			];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?osm\.org\/go\/[0-9a-zA-Z\-]+\?([a-zA-Z]+)=(\d+)$/i', $url, $matches);
		if (count($matches) > 2) {
			return [
				'type' => $matches[1],
				'id' => $matches[2],
			];
		}

		return null;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the GitHub account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
