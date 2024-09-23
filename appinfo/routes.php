<?php
/**
 * Nextcloud - OpenStreetMap
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

return [
	'routes' => [
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#setSensitiveAdminConfig', 'url' => '/sensitive-admin-config', 'verb' => 'PUT'],
		['name' => 'osmAPI#nominatimSearch', 'url' => '/search', 'verb' => 'GET'],
		['name' => 'osmAPI#getRasterTile', 'url' => '/tiles/{service}/{x}/{y}/{z}', 'verb' => 'GET'],
		['name' => 'osmAPI#getOsrmRoutes', 'url' => '/osrm/route/{profile}/{coordinates}', 'verb' => 'GET'],

		['name' => 'maptiler#getMapTilerStyle', 'url' => '/maptiler/maps/{version}/style.json', 'verb' => 'GET'],
		['name' => 'maptiler#getMapTilerFont', 'url' => '/maptiler/fonts/{fontstack}/{range}.pbf', 'verb' => 'GET'],
		['name' => 'maptiler#getMapTilerTiles', 'url' => '/maptiler/tiles/{version}/tiles.json', 'verb' => 'GET'],
		['name' => 'maptiler#getMapTilerTile', 'url' => '/maptiler/tiles/{version}/{z}/{x}/{y}.{ext}', 'verb' => 'GET'],
		['name' => 'maptiler#getMapTilerSprite', 'url' => '/maptiler/maps/{version}/sprite.{ext}', 'verb' => 'GET'],
		['name' => 'maptiler#getMapTilerResource', 'url' => '/maptiler/resources/{name}', 'verb' => 'GET'],
	],
];
