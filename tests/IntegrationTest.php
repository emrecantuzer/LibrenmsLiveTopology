<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\MapController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\RenderController;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function test_controllers_exist()
    {
        $this->assertTrue(class_exists(MapController::class));
        $this->assertTrue(class_exists(RenderController::class));
    }

    public function test_services_exist()
    {
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\PortUtilService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\AlertService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\LinkDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\MapVersionService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\MapService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\NodeService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\LinkService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\AutoDiscoveryService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\NodeDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\DeviceDataService'));
        $this->assertTrue(class_exists('LibreNMS\Plugins\LibreLiveTopology\Services\LinkDataService'));
    }

    public function test_render_controller_with_dependencies()
    {
        $nodeDataService = $this->createMock(
            \LibreNMS\Plugins\LibreLiveTopology\Services\NodeDataService::class
        );

        $mapService = $this->createMock(
            \LibreNMS\Plugins\LibreLiveTopology\Services\MapService::class
        );

        $controller = new \LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\RenderController($nodeDataService, $mapService);

        $this->assertInstanceOf(\LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\RenderController::class, $controller);
    }
}
