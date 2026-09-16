<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use LibreNMS\Plugins\LibreLiveTopology\RRD\RRDTool;
use LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService;
use PHPUnit\Framework\TestCase;

class RrdTrafficUnitsTest extends TestCase
{
    public function test_port_octets_are_converted_to_bits_once_and_directions_stay_distinct(): void
    {
        $tool = $this->createMock(RRDTool::class);
        $tool->expects($this->once())->method('getLastValues')->with('port-id101.rrd')
            ->willReturn(['traffic_in' => 1000000.25, 'traffic_out' => 125000]);
        $service = new RrdDataService($tool, '/unused');
        $method = new \ReflectionMethod($service, 'fetchTrafficFromRrd');
        $method->setAccessible(true);
        $this->assertSame(['in' => 8000002, 'out' => 1000000], $method->invoke($service, 'port-id101.rrd'));
    }
}
