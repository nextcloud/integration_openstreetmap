<?php

declare(strict_types=1);
/**
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
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
 *
 */
namespace OCA\Osm\Listener;

use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class CSPListener implements IEventListener {
	private $request;

	public function __construct(IRequest $request,
								IConfig $config
	) {
		$this->request = $request;
		$this->config = $config;
	}

	public function handle(Event $event): void {
		if (!$event instanceof AddContentSecurityPolicyEvent) {
			return;
		}

		if (!$this->isPageLoad()) {
			return;
		}

		$policy = new EmptyContentSecurityPolicy();
		$policy->addAllowedFrameDomain('https://www.openstreetmap.org');

		$policy->addAllowedImageDomain('https://*.tile.openstreetmap.org');
		$policy->addAllowedImageDomain('https://api.maptiler.com');

		$policy->addAllowedConnectDomain('https://*.tile.openstreetmap.org');
		$policy->addAllowedConnectDomain('https://server.arcgisonline.com');
		$policy->addAllowedConnectDomain('https://*.tile.stamen.com');
		$policy->addAllowedConnectDomain('https://api.maptiler.com');

		$event->addPolicy($policy);
	}

	private function isPageLoad(): bool {
		$scriptNameParts = explode('/', $this->request->getScriptName());
		return end($scriptNameParts) === 'index.php';
	}
}
