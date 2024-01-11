<?php

namespace OCA\Osm\Tests;

use OC\Http\Client\ClientService;
use OC\L10N\L10N;
use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\OsmAPIService;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class OsmAPIServiceTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(L10N::class);
		$this->clientService = $this->createMock(ClientService::class);

		$this->service = new OsmAPIService($this->logger, $this->l10n, $this->clientService);
	}

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('integration_openstreetmap', $app::APP_ID);
	}

	public function testGetLinkFromCoordinates() {
		$testData = [
			[
				'lat' => 11.1, 'lon' => 22.2, 'zoom' => 15, 'includeMarker' => true,
				'resultLink' => 'https://www.openstreetmap.org/?mlat=11.1&mlon=22.2#map=15/11.1/22.2',
			],
			[
				'lat' => 1.1, 'lon' => 2.2, 'zoom' => 5, 'includeMarker' => true,
				'resultLink' => 'https://www.openstreetmap.org/?mlat=1.1&mlon=2.2#map=5/1.1/2.2',
			],
			[
				'lat' => 11.1, 'lon' => 22.2, 'zoom' => 15, 'includeMarker' => false,
				'resultLink' => 'https://www.openstreetmap.org/#map=15/11.1/22.2',
			],
			[
				'lat' => 1.1, 'lon' => 2.2, 'zoom' => 5, 'includeMarker' => false,
				'resultLink' => 'https://www.openstreetmap.org/#map=5/1.1/2.2',
			],
		];
		foreach ($testData as $d) {
			$this->assertEquals($d['resultLink'], $this->service->getLinkFromCoordinates($d['lat'], $d['lon'], $d['zoom'], $d['includeMarker']));
		}
	}
}
