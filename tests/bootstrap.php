<?php

use OCA\Osm\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Server;

require_once __DIR__ . '/../../../tests/bootstrap.php';

Server::get(IAppManager::class)->loadApp(Application::APP_ID);
OC_Hook::clear();
