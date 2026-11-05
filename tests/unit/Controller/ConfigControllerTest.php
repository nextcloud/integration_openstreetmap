<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Osm\Tests\Unit\Controller;

use OCA\Osm\AppInfo\Application;
use OCA\Osm\Controller\ConfigController;
use OCP\Config\IUserConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConfigControllerTest extends TestCase {

	private IUserConfig&MockObject $userConfig;
	private IAppConfig&MockObject $appConfig;
	private IEventDispatcher&MockObject $eventDispatcher;
	/** @var list<CriticalActionPerformedEvent> */
	private array $auditEvents = [];
	private ConfigController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->auditEvents = [];
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (object $event): void {
				if ($event instanceof CriticalActionPerformedEvent) {
					$this->auditEvents[] = $event;
				}
			});

		$this->controller = new ConfigController(
			'integration_openstreetmap',
			$this->createMock(IRequest::class),
			$this->userConfig,
			$this->appConfig,
			'admin',
			$this->eventDispatcher,
		);
	}

	/**
	 * @param array<string, string> $stored key/value pairs currently stored in the app config
	 */
	private function mockAppConfig(array $stored): void {
		$this->appConfig->method('hasKey')
			->willReturnCallback(fn (string $app, string $key, ?bool $lazy = false): bool => array_key_exists($key, $stored));
		$this->appConfig->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default = '', bool $lazy = false): string => $stored[$key] ?? $default);
	}

	public function testSetAdminConfigAuditLogsChangedValues(): void {
		$this->mockAppConfig([
			'search_location_enabled' => '1',
			'proxy_osm' => '1',
		]);
		$this->appConfig->expects($this->exactly(2))
			->method('setValueString')
			->willReturnCallback(function (string $app, string $key, string $value, bool $lazy): bool {
				self::assertSame(Application::APP_ID, $app);
				self::assertFalse($lazy, 'Admin settings must not be stored lazy');
				self::assertSame($key === 'search_location_enabled' ? '0' : '0', $value, 'New value must be stored');
				return true;
			});

		$this->controller->setAdminConfig([
			'search_location_enabled' => '0',
			'proxy_osm' => '0',
		]);

		$this->assertCount(2, $this->auditEvents, 'Should audit both changed settings');
		[$searchEvent, $proxyEvent] = $this->auditEvents;
		self::assertSame(
			['setting' => 'search_location_enabled', 'oldValue' => '1', 'newValue' => '0', 'user' => 'admin'],
			$searchEvent->getParameters(),
		);
		self::assertSame(
			['setting' => 'proxy_osm', 'oldValue' => '1', 'newValue' => '0', 'user' => 'admin'],
			$proxyEvent->getParameters(),
		);
	}

	public function testSetAdminConfigAuditLogsPreviouslyUnsetKey(): void {
		$this->mockAppConfig([]);
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'proxy_osm', '1', false);

		$this->controller->setAdminConfig(['proxy_osm' => '1']);

		$this->assertCount(1, $this->auditEvents, 'Should audit the new setting');
		self::assertSame(
			['setting' => 'proxy_osm', 'oldValue' => null, 'newValue' => '1', 'user' => 'admin'],
			$this->auditEvents[0]->getParameters(),
		);
	}

	public function testSetAdminConfigDoesNotAuditUnchangedValues(): void {
		$this->mockAppConfig(['search_location_enabled' => '1']);
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'search_location_enabled', '1', false);
		$this->eventDispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->controller->setAdminConfig(['search_location_enabled' => '1']);

		self::assertCount(0, $this->auditEvents);
	}

	public function testSetAdminConfigRejectsApiKeyWithoutAuditLog(): void {
		$this->eventDispatcher->expects($this->never())
			->method('dispatchTyped');
		$this->appConfig->expects($this->never())
			->method('setValueString');

		$response = $this->controller->setAdminConfig(['maptiler_api_key' => 'secret']);

		self::assertSame([], $response->getData());
		self::assertCount(0, $this->auditEvents);
	}

	public function testSetSensitiveAdminConfigAuditLogsChangedValueWithoutStoringIt(): void {
		$this->mockAppConfig(['maptiler_api_key' => 'old-secret']);
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'maptiler_api_key', 'new-secret', false, true);

		$this->controller->setSensitiveAdminConfig(['maptiler_api_key' => 'new-secret']);

		$this->assertCount(1, $this->auditEvents, 'Should audit the changed sensitive setting');
		$event = $this->auditEvents[0];
		self::assertSame(
			['setting' => 'maptiler_api_key', 'oldValue' => 'a redacted value', 'newValue' => 'a redacted value', 'user' => 'admin'],
			$event->getParameters(),
		);
		self::assertTrue($event->getObfuscateParameters(), 'Values are sensitive, the event must ask for obfuscation');
		self::assertDoesNotMatchRegularExpression('/(old|new)-secret/', $event->getLogMessage(), 'The log message must not contain the secrets');
	}

	public function testSetSensitiveAdminConfigAuditLogsClearingValue(): void {
		$this->mockAppConfig(['maptiler_api_key' => 'old-secret']);
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'maptiler_api_key', '', false, true);

		$this->controller->setSensitiveAdminConfig(['maptiler_api_key' => '']);

		$this->assertCount(1, $this->auditEvents, 'Should audit the deleted sensitive setting');
		self::assertSame(
			['setting' => 'maptiler_api_key', 'oldValue' => 'a redacted value', 'newValue' => 'unset', 'user' => 'admin'],
			$this->auditEvents[0]->getParameters(),
		);
	}

	public function testSetSensitiveAdminConfigDoesNotAuditUnchangedValue(): void {
		$this->mockAppConfig(['maptiler_api_key' => 'secret']);
		$this->eventDispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->controller->setSensitiveAdminConfig(['maptiler_api_key' => 'secret']);

		self::assertCount(0, $this->auditEvents);
	}
}
