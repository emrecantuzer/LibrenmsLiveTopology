<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use LibreNMS\Plugins\LibreLiveTopology\Services\DeviceDataService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DeviceDataServiceStatusTest extends TestCase
{
    public function test_missing_status_is_unknown_but_explicit_zero_is_down(): void
    {
        $service = (new ReflectionClass(DeviceDataService::class))->newInstanceWithoutConstructor();
        $parse = new \ReflectionMethod(DeviceDataService::class, 'parseDeviceStatus');

        foreach ([null, (object) [], ['status' => null], ['status' => ''], ['status' => 'offline?'], ['status' => 2], ['status' => 1.5]] as $device) {
            $this->assertSame('unknown', $parse->invoke($service, $device));
        }
        foreach ([(object) ['status' => 0], ['status' => '0'], ['status' => 'down']] as $device) {
            $this->assertSame('down', $parse->invoke($service, $device));
        }
        foreach ([(object) ['status' => 1], ['status' => '1'], ['status' => 'up']] as $device) {
            $this->assertSame('up', $parse->invoke($service, $device));
        }
    }
}
