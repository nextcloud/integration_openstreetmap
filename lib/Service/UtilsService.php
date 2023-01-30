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

use DOMDocument;
use DOMXPath;
use Exception;
use OCA\Osm\AppInfo\Application;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use Throwable;

class UtilsService {

	private IClientService $clientService;
	private LoggerInterface $logger;

	public function __construct (string         $appName,
								 LoggerInterface $logger,
								 IClientService $clientService) {
		$this->clientService = $clientService;
		$this->logger = $logger;
	}

	/**
	 * @param string $sc
	 * @return array
	 */
	public function decodeOsmShortLink(string $sc): array {
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_~';
		$x = 0;
		$y = 0;
		$z = -8;

		for ($i = 0; $i < strlen($sc); $i++) {
    		$ch = $sc[$i];
			$digit = strpos($chars, $ch);
			if ($digit === false) {
				break;
			}
			// distribute 6 bits into x and y
			$x <<= 3;
			$y <<= 3;
			for ($j = 2; $j >= 0; $j--) {
				$x |= (($digit & (1 << ($j + $j + 1))) === 0 ? 0 : (1 << $j));
				$y |= (($digit & (1 << ($j + $j))) === 0 ? 0 : (1 << $j));
			}
			$z += 3;
		}
		$x = $x * pow(2,2 - 3 * $i) * 90 - 180;
		$y = $y * pow(2,2 - 3 * $i) * 45 -  90;
		// adjust z
		if ($i < strlen($sc) && $sc[$i] === '-') {
			$z -= 2;
			if ($i + 1 < strlen($sc) && $sc[$i + 1] === '-') {
				$z++;
			}
		}
		return [
			'lat' => $y,
			'lon' => $x,
			'zoom' => $z,
		];
	}

	/**
	 * @param string $hash
	 * @return array|null
	 */
	public function decodeGoogleMapsShortLink(string $hash): ?string {
		$client = $this->clientService->newClient();
		$url = 'https://goo.gl/maps/' . $hash;
		$options = [];
		try {
			$response = $client->get($url, $options);
			$respCode = $response->getStatusCode();
			if ($respCode < 400) {
				$body = $response->getBody();
				$dom = new DomDocument();
				$dom->loadHTML($body);
				$xpath = new DOMXpath($dom);
				$elements = $xpath->query("//head/meta[@itemprop='image']");
				if (!empty($elements) && isset($elements[0])) {
					return $elements[0]->getAttribute('content');
				}
			}
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Google short link redirect error: ' . $e->getMessage(), ['app' => Application::APP_ID]);
		}

		return null;
	}
}
