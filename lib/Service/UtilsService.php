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

	public function __construct(
		string $appName,
		private LoggerInterface $logger,
		private IClientService $clientService,
	) {
	}

	public function decodeOrganicMapsShortLink(string $code): array {
		$b64Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

		$m_base64ReverseCharTable = [];
		for ($i = 0; $i < 256; ++$i) {
			$m_base64ReverseCharTable[$i] = 255;
		}
		for ($i = 0; $i < 64; ++$i) {
			$c = $b64Chars[$i];
			$m_base64ReverseCharTable[ord($c)] = $i;
		}

		$zChar = $code[0];
		$zoomI = $m_base64ReverseCharTable[ord($zChar)];
		$z = $zoomI / 4 + 4;

		//////////// DecodeLatLonToInt

		$coordsString = substr($code, 1);
		$latInt = 0;
		$lonInt = 0;

		$kMaxPointBytes = 10;
		$kMaxCoordBits = $kMaxPointBytes * 3;

		$shift = $kMaxCoordBits - 3;
		for ($i = 0; $i < strlen($coordsString); ++$i, $shift -= 3) {
			$a = $m_base64ReverseCharTable[ord($coordsString[$i])];
			if ($a >= 64) {
				return [
					'lat' => 0,
					'lon' => 0,
					'zoom' => $z,
				];
			}

			$lat1 = ((($a >> 5) & 1) << 2 | (($a >> 3) & 1) << 1 | (($a >> 1) & 1));
			$lon1 = ((($a >> 4) & 1) << 2 | (($a >> 2) & 1) << 1 | ($a & 1));
			$latInt |= $lat1 << $shift;
			$lonInt |= $lon1 << $shift;
		}

		$middleOfSquare = 1 << (3 * ($kMaxPointBytes - strlen($coordsString)) - 1);
		$latInt += $middleOfSquare;
		$lonInt += $middleOfSquare;

		/////////////// DecodeLatFromInt

		$maxValue = (1 << $kMaxCoordBits) - 1;
		$lat = $latInt / $maxValue * 180 - 90;
		$lon = $lonInt / ($maxValue + 1.0) * 360.0 - 180;

		return [
			'lat' => $lat,
			'lon' => $lon,
			'zoom' => $z,
		];
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
		$x = $x * pow(2, 2 - 3 * $i) * 90 - 180;
		$y = $y * pow(2, 2 - 3 * $i) * 45 - 90;
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
	 * @return string|null
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
		} catch (Exception|Throwable $e) {
			$this->logger->warning('Google short link redirect error: ' . $e->getMessage(), ['app' => Application::APP_ID]);
		}

		return null;
	}

	/**
	 * @param string $hash
	 * @return string|null
	 */
	public function decodeGoogleMapsAppShortLink(string $hash): ?string {
		$client = $this->clientService->newClient();
		$url = 'https://maps.app.goo.gl/' . $hash;
		$options = ['allow_redirects' => false];
		try {
			$response = $client->get($url, $options);
			$respCode = $response->getStatusCode();
			if ($respCode < 400 && $response->getHeader('Location')) {
				return $response->getHeader('Location');
			}
		} catch (Exception|Throwable $e) {
			$this->logger->warning('Google short link decode error: ' . $e->getMessage(), ['app' => Application::APP_ID]);
		}

		return null;
	}
}
