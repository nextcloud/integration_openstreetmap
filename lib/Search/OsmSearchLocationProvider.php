<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Osm\Search;

use OCA\Osm\Service\OsmAPIService;
use OCA\Osm\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class OsmSearchLocationProvider implements IProvider {

	private IAppManager $appManager;
	private IL10N $l10n;
	private IConfig $config;
	private OsmAPIService $osmAPIService;
	private IURLGenerator $urlGenerator;

	public function __construct(IAppManager        $appManager,
								IL10N              $l10n,
								IConfig            $config,
								IURLGenerator      $urlGenerator,
								OsmAPIService $osmAPIService) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->osmAPIService = $osmAPIService;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'openstreetmap-search-location';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('OpenStreetMap locations');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer OpenStreetMap results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? intval($offset) : 0;

		$searchLocationEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_location_enabled', '1') === '1';
		if (!$searchLocationEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$searchResult = $this->osmAPIService->searchLocation($user->getUID(), $term, $offset, $limit);
		if (isset($searchResult['error'])) {
			$items = [];
		} else {
			$items = $searchResult;
		}

		$formattedResults = array_map(function (array $entry): OsmSearchResultEntry {
			return new OsmSearchResultEntry(
				$this->getThumbnailUrl($entry),
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLink($entry),
				$this->getIconUrl($entry),
				false
			);
		}, $items);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	protected function getMainText(array $entry): string {
		return $entry['display_name'];
	}

	protected function getSubline(array $entry): string {
		return $entry['type'];
	}

	protected function getLink(array $entry): string {
//		return $this->osmAPIService->getLinkFromCoordinates((float) $entry['lat'], (float) $entry['lon']);
		return $this->osmAPIService->getLinkFromOsmId($entry['osm_id'], $entry['osm_type']);
	}

	protected function getIconUrl(array $entry): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'logo.svg');
	}

	protected function getThumbnailUrl(array $entry): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'logo.svg');
	}
}
