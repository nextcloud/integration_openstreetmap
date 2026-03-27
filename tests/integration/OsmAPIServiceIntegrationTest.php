<?php

namespace OCA\Osm\Tests\Integration;

use OCA\Osm\Reference\OsmPointReferenceProvider;
use OCA\Osm\Service\OsmAPIService;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class OsmAPIServiceIntegrationTest extends TestCase {

	private OsmAPIService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->service = Server::get(OsmAPIService::class);
	}

	/**
	 * Uses the docblock example values from OsmAPIService::geocode and checks
	 * that the response can be consumed by reference providers without crashing.
	 */
	public function testGeocode(): void {
		$result = $this->service->geocode(44.3383486, 1.2086886, false);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('error', $result, json_encode($result));

		$this->assertArrayHasKey('lat', $result);
		$this->assertArrayHasKey('lon', $result);

		$coords = [
			'lat' => 44.3383486,
			'lon' => 1.2086886,
			'zoom' => 14,
		];
		$geoLink = 'geo:' . $coords['lat'] . ':' . $coords['lon'] . '?z=' . $coords['zoom'];

		$title = $result['display_name'] ?? $geoLink;
		$this->assertIsString($title);

		if (isset($result['osm_type'], $result['osm_id'])) {
			$link = $this->service->getLinkFromOsmId((int)$result['osm_id'], (string)$result['osm_type']);
			$this->assertIsString($link);
		}
	}

	/**
	 * Uses the docblock example value from OsmAPIService::searchLocation and
	 * checks that entries contain the fields used by OsmSearchLocationProvider.
	 */
	public function testSearchLocation(): void {
		$result = $this->service->searchLocation('montcuq', 'json', [
			'addressdetails' => 1,
			'extratags' => 1,
			'namedetails' => 1,
		], 0, 5);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('error', $result, json_encode($result));
		$this->assertNotEmpty($result, 'Searching for "montcuq" should return at least one result');

		foreach ($result as $i => $entry) {
			$prefix = 'search[' . $i . ']';

			$this->assertArrayHasKey('display_name', $entry, "$prefix missing display_name");
			$this->assertArrayHasKey('type', $entry, "$prefix missing type");
			$this->assertArrayHasKey('osm_id', $entry, "$prefix missing osm_id");
			$this->assertArrayHasKey('osm_type', $entry, "$prefix missing osm_type");

			$mainText = $entry['display_name'];
			$subline = $entry['type'];
			$link = $this->service->getLinkFromOsmId((int)$entry['osm_id'], (string)$entry['osm_type']);

			$this->assertIsString($mainText);
			$this->assertIsString($subline);
			$this->assertIsString($link);
		}
	}

	/**
	 * Uses the docblock example values from OsmAPIService::getLocationInfo and
	 * checks that the returned data can be used by OsmLocationReferenceProvider.
	 */
	public function testGetLocationInfo(): void {
		$result = $this->service->getLocationInfo(87515, 'relation');

		$this->assertNotNull($result);
		$this->assertArrayNotHasKey('error', $result, json_encode($result));

		$this->assertArrayHasKey('display_name', $result);
		$this->assertArrayHasKey('lat', $result);
		$this->assertArrayHasKey('lon', $result);

		$this->assertIsString($result['display_name']);

		$locationInfo = OsmPointReferenceProvider::getFragmentInfo('https://www.openstreetmap.org/relation/87515#map=14/44.7209/4.5877', $result);
		$locationInfo['map_center'] = [
			'lat' => 44.7209,
			'lon' => 4.5877,
		];
		$locationInfo['zoom'] = 14;
		$locationInfo['marker_coordinates'] = [
			'lat' => $locationInfo['lat'],
			'lon' => $locationInfo['lon'],
		];

		$this->assertArrayHasKey('marker_coordinates', $locationInfo);
		$this->assertArrayHasKey('map_center', $locationInfo);
		$this->assertArrayHasKey('zoom', $locationInfo);
	}
}
