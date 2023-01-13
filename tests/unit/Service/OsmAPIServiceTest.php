<?php

namespace OCA\Osm\Tests;


use OCA\Osm\AppInfo\Application;
use OCA\Osm\Service\OsmAPIService;

class OsmAPIServiceTest extends \PHPUnit\Framework\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('integration_openstreetmap', $app::APP_ID);
	}
}
