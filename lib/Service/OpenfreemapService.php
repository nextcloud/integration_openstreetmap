<?php

/**
 * Nextcloud - Osm
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2026
 */

namespace OCA\Osm\Service;

use Exception;
use OCA\Osm\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;

class OpenfreemapService {
	private IClient $client;

	private const OPENFREEMAP_HOST = 'https://tiles.openfreemap.org';

	public function __construct(
		IClientService $clientService,
		private IURLGenerator $urlGenerator,
	) {
		$this->client = $clientService->newClient();
	}

	private function getProxyBase(): string {
		$url = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.map.getOpenFreeMapStyle', ['style' => 'aaa']);
		return str_replace('/openfreemap/styles/aaa', '/openfreemap', $url);
	}

	private function getVectorProxyRequestOptions(): array {
		$instanceUrl = $this->urlGenerator->getBaseUrl();
		return [
			'headers' => [
				'Origin' => $instanceUrl,
			],
		];
	}

	/**
	 * @param string $path
	 * @return array [string $content, string $contentType]
	 * @throws Exception
	 */
	private function getOpenFreeMapResponse(string $path): array {
		$url = self::OPENFREEMAP_HOST . '/' . $path;
		$response = $this->client->get($url, $this->getVectorProxyRequestOptions());
		$body = $response->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			if ($content === false) {
				throw new Exception('No content');
			}
			$body = $content;
		}
		return [$body, $response->getHeader('content-type')];
	}

	/**
	 * @param string $style
	 * @return array
	 * @throws Exception
	 */
	public function getOpenFreeMapStyle(string $style): array {
		$content = $this->getOpenFreeMapResponse('styles/' . $style)[0];
		$replacementUrl = $this->getProxyBase();
		$styleJson = json_decode(str_replace(self::OPENFREEMAP_HOST, $replacementUrl, $content), true);
		foreach ($styleJson['layers'] as $i => $layer) {
			if (isset($layer['layout']) && is_array($layer['layout']) && empty($layer['layout'])) {
				$styleJson['layers'][$i]['layout'] = (object)[];
			}
		}
		return $styleJson;
	}

	/**
	 * Proxy any OpenFreeMap resource (fonts, sprites, tiles.json, tiles, ...).
	 * JSON responses have their upstream URLs rewritten to the proxy routes
	 * of this instance so the map client keeps using the proxy afterwards.
	 *
	 * @param string $path
	 * @return array
	 * @throws Exception
	 */
	public function getOpenFreeMapResource(string $path): array {
		[$body, $contentType] = $this->getOpenFreeMapResponse($path);
		if (str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[')) {
			$body = str_replace(self::OPENFREEMAP_HOST, $this->getProxyBase(), $body);
		}
		return [
			'body' => $body,
			'content-type' => $contentType ?: 'application/octet-stream',
		];
	}
}
