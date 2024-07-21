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
		['name' => 'osmAPI#nominatimSearch', 'url' => '/search', 'verb' => 'GET'],
		['name' => 'osmAPI#getRasterTile', 'url' => '/tiles/{service}/{x}/{y}/{z}', 'verb' => 'GET'],
		['name' => 'osmAPI#getMapTilerFont', 'url' => '/fonts/{fontstack}/{range}.pbf', 'verb' => 'GET'],
	],
];
