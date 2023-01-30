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
use OCA\Osm\Service\UtilsService;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\OsmAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IL10N;

use OCP\IURLGenerator;

class DuckduckgoReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_location';

	private OsmAPIService $osmAPIService;
	private ?string $userId;
	private IConfig $config;
	private ReferenceManager $referenceManager;
	private IURLGenerator $urlGenerator;
	private LinkReferenceProvider $linkReferenceProvider;
	private UtilsService $utilsService;

	public function __construct(OsmAPIService $osmAPIService,
								IConfig $config,
								IURLGenerator $urlGenerator,
								ReferenceManager $referenceManager,
								LinkReferenceProvider $linkReferenceProvider,
								UtilsService $utilsService,
								?string $userId) {
		$this->osmAPIService = $osmAPIService;
		$this->userId = $userId;
		$this->config = $config;
		$this->referenceManager = $referenceManager;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
		$this->utilsService = $utilsService;
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
		// https://duckduckgo.com/?t=ffab&q=Privas&ia=web&iaxm=maps&strict_bbox=0&bbox=44.745808303066084%2C4.539061284793959%2C44.69692460306608%2C4.6507849152060174&iax=images
		return preg_match('/^(?:https?:\/\/)?(?:www\.)?duckduckgo\.com\/.*bbox=[+-]?\d+\.\d+%2C[+-]?\d+\.\d+%2C[+-]?\d+\.\d+%2C[+-]?\d+\.\d+/i', $referenceText) === 1;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$coords = $this->getCoordinates($referenceText);
			if ($coords !== null) {
				$reference = new Reference($referenceText);
				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$coords,
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
		preg_match('/^(?:https?:\/\/)?(?:www\.)?duckduckgo\.com\/.*bbox=([+-]?\d+\.\d+)%2C([+-]?\d+\.\d+)%2C([+-]?\d+\.\d+)%2C([+-]?\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 4) {
			return [
				'boundingbox' => [
					(float) $matches[1],
					(float) $matches[3],
					(float) $matches[2],
					(float) $matches[4],
				],
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
