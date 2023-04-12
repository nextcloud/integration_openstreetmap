<?php
/**
 * Nextcloud - OpenStreetMap
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Osm\AppInfo;

use Closure;
use OCA\Osm\Listener\CSPListener;
use OCA\Osm\Listener\OsmReferenceListener;
use OCA\Osm\Reference\BingReferenceProvider;
use OCA\Osm\Reference\DuckduckgoReferenceProvider;
use OCA\Osm\Reference\GoogleMapsReferenceProvider;
use OCA\Osm\Reference\HereMapsReferenceProvider;
use OCA\Osm\Reference\OsmLocationReferenceProvider;
use OCA\Osm\Reference\OsmPointReferenceProvider;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\IConfig;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

use OCA\Osm\Search\OsmSearchLocationProvider;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_openstreetmap';
	public const OSM_URL = 'https://www.openstreetmap.org';

	public const DEFAULT_MAPTILER_API_KEY = 'get_your_own_OpIi9ZULNHzrESv6T2vL';
	public const DEFAULT_MAPBOX_API_KEY = 'pk.eyJ1IjoiZW5laWx1aiIsImEiOiJjazE4Y2xvajcxbGJ6M29xajY1bThuNjRnIn0.hZ4f0_kiPK5OvLBQ1GxVmg';
	public const DEFAULT_SEARCH_LOCATION_ENABLED_VALUE = '0';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->container = $container;
		$this->config = $container->query(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerSearchProvider(OsmSearchLocationProvider::class);

		$context->registerReferenceProvider(OsmLocationReferenceProvider::class);
		$context->registerReferenceProvider(OsmPointReferenceProvider::class);
		$context->registerReferenceProvider(GoogleMapsReferenceProvider::class);
		$context->registerReferenceProvider(HereMapsReferenceProvider::class);
		$context->registerReferenceProvider(DuckduckgoReferenceProvider::class);
		$context->registerReferenceProvider(BingReferenceProvider::class);

		$context->registerEventListener(RenderReferenceEvent::class, OsmReferenceListener::class);
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
	}

	public function registerNavigation(IUserSession $userSession): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			$container = $this->getContainer();

			if ($this->config->getUserValue($userId, self::APP_ID, 'navigation_enabled', '0') === '1') {
				$l10n = $container->get(IL10N::class);
				$navName = $l10n->t('OpenStreetMap');
				$container->get(INavigationManager::class)->add(function () use ($container, $navName) {
					$urlGenerator = $container->get(IURLGenerator::class);
					return [
						'id' => self::APP_ID,
						'order' => 10,
						'href' => self::OSM_URL,
						'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
						'name' => $navName,
						'target' => '_blank',
					];
				});
			}
		}
	}
}

