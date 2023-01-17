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

class UtilsService {

	public function __construct (string $appName) {
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
}
