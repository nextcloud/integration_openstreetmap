<?php
/**
 * @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Osm\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;

class BingReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_location';

	public function __construct(
		private IConfig $config,
		private ReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private ?string $userId
	) {
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

		return $this->getCoordinates($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$coords = $this->getCoordinates($referenceText);
			if ($coords !== null) {
				$pointInfo = [];
				$pointInfo['zoom'] = $coords['zoom'] ?? 14;
				$pointInfo['map_center'] = [
					'lat' => $coords['lat'],
					'lon' => $coords['lon'],
				];
				if (isset($coords['markerLat'], $coords['markerLon'])) {
					$pointInfo['marker_coordinates'] = [
						'lat' => $coords['markerLat'],
						'lon' => $coords['markerLon'],
					];
				}

				$reference = new Reference($referenceText);
				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$pointInfo,
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
		// supported link examples:
		// https://www.bing.com/maps/?cp=43.622486%7E3.864137&lvl=16.4
		// https://www.bing.com/maps/?cp=43.622486~3.864137&lvl=16.4
		// https://www.bing.com/maps?osid=f8b7dd22-67bd-4402-be5d-711d01f1b13c&cp=44.722467~4.568366&lvl=14.68&pi=0&imgid=5d256382-55b8-4bb9-a0b9-74773be9bfc9&v=2&sV=2&form=S00027

		preg_match('/^(?:https?:\/\/)?(?:www\.)?bing\.com\/maps\/\?cp=([+-]?\d+\.\d+)%7E([+-]?\d+\.\d+)&lvl=(\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'lat' => (float) $matches[1],
				'lon' => (float) $matches[2],
				'zoom' => (int) $matches[3],
			];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?bing\.com\/maps\/\?cp=([+-]?\d+\.\d+)~([+-]?\d+\.\d+)&lvl=(\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'lat' => (float) $matches[1],
				'lon' => (float) $matches[2],
				'zoom' => (int) $matches[3],
			];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?bing\.com\/maps\/?\?osid=[-a-z0-9]+&cp=([+-]?\d+\.\d+)~([+-]?\d+\.\d+)&lvl=(\d+\.\d+)/i', $url, $matches);
		if (count($matches) > 3) {
			return [
				'lat' => (float) $matches[1],
				'lon' => (float) $matches[2],
				'zoom' => (int) $matches[3],
				// not accurate
//				'markerLat' => (float) $matches[1],
//				'markerLon' => (float) $matches[2],
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
