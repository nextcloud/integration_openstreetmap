<?php

/**
 * Nextcloud - Osm
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Osm\Service;

use Exception;
use OCA\Osm\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;

class MaptilerService {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private IURLGenerator $urlGenerator,
	) {
		$this->client = $clientService->newClient();
	}

	private function getReplacementUrl(): string {
		$replacementUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.maptiler.getMapTilerStyle', ['version' => 'aaa']);
		return str_replace('/maptiler/maps/aaa/style.json', '/maptiler', $replacementUrl);
	}

	private function getVectorProxyRequestOptions() {
		$instanceUrl = $this->urlGenerator->getBaseUrl();
		return [
			'headers' => [
				'Origin' => $instanceUrl,
			],
		];
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerStyle(string $version, ?string $key = null): array {
		$url = 'https://api.maptiler.com/maps/' . $version . '/style.json';
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
		} else {
			$content = $body;
		}
		$replacementUrl = $this->getReplacementUrl();
		$style = json_decode(preg_replace('/https:\/\/api\.maptiler\.com/', $replacementUrl, $content), true);
		foreach ($style['layers'] as $i => $layer) {
			if (is_array($layer['layout']) && empty($layer['layout'])) {
				$style['layers'][$i]['layout'] = (object)[];
			}
		}
		return $style;
	}

	/**
	 * @param string $fontstack
	 * @param string $range
	 * @param string|null $key
	 * @return string|null
	 * @throws Exception
	 */
	public function getMapTilerFont(string $fontstack, string $range, ?string $key = null): ?string {
		// https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey
		$url = 'https://api.maptiler.com/fonts/' . $fontstack . '/' . $range . '.pbf';
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			return $content === false
				? null
				: $content;
		}
		return $body;
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerTiles(string $version, ?string $key = null): array {
		$url = 'https://api.maptiler.com/tiles/' . $version . '/tiles.json';
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			if ($content === false) {
				throw new Exception('No content');
			}
		} else {
			$content = $body;
		}
		$replacementUrl = $this->getReplacementUrl();
		return json_decode(preg_replace('/https:\/\/api\.maptiler\.com/', $replacementUrl, $content), true);
	}

	/**
	 * @param string $version
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string $ext
	 * @param string|null $key
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerTile(string $version, int $x, int $y, int $z, string $ext, ?string $key = null): array {
		$url = 'https://api.maptiler.com/tiles/' . $version . '/' . strval($z) . '/' . strval($x) . '/' . strval($y) . '.' . $ext;
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$response = $this->client->get($url, $this->getVectorProxyRequestOptions());
		$body = $response->getBody();
		$headers = $response->getHeaders();
		return [
			'body' => $body,
			'headers' => $headers,
		];
	}

	/**
	 * @param string $version
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerSpriteJson(string $version): array {
		$url = 'https://api.maptiler.com/maps/' . $version . '/sprite.json';
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			if ($content === false) {
				throw new Exception('No content');
			}
			return json_decode($content, true);
		}
		return json_decode($body, true);
	}

	/**
	 * @param string $version
	 * @param string $ext
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerSpriteImage(string $version, string $ext): array {
		$url = 'https://api.maptiler.com/maps/' . $version . '/sprite.' . $ext;
		$response = $this->client->get($url, $this->getVectorProxyRequestOptions());
		$body = $response->getBody();
		$headers = $response->getHeaders();
		return [
			'body' => $body,
			'headers' => $headers,
		];
	}

	/**
	 * @param string $name
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerResource(string $name): array {
		$url = 'https://api.maptiler.com/resources/' . $name;
		$response = $this->client->get($url, $this->getVectorProxyRequestOptions());
		$body = $response->getBody();
		$headers = $response->getHeaders();
		return [
			'body' => $body,
			'headers' => $headers,
		];
	}
}
