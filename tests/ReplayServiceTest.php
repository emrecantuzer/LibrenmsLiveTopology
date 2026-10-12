<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\MapVersion;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapVersionService;
use LibreNMS\Plugins\LibreLiveTopology\Services\ReplayService;
use LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService;

/**
 * Replay must follow the live link rules in PortUtilService::linkUtilBits():
 * one source endpoint, B reversed when A is absent, max(in, out) utilization,
 * and port speed as the bandwidth fallback.
 */
class ReplayServiceTest extends TestCase
{
    private const TS = 1_700_000_000;

    /**
     * @param array<int, array{in:int,out:int}> $traffic
     * @param array<int, int|null>              $speeds
     */
    private function replayLink(array $link, array $traffic, array $speeds = []): array
    {
        $rrd = $this->createMock(RrdDataService::class);
        $rrd->method('getPortTrafficAt')->willReturnCallback(
            fn (int $portId, int $ts) => $traffic[$portId] ?? null
        );
        $rrd->method('getPortSpeed')->willReturnCallback(
            fn (int $portId) => $speeds[$portId] ?? null
        );

        $version = new MapVersion();
        $version->id = 3;
        $version->config_snapshot = ['links' => [$link['id'] => $link]];

        $versions = $this->createMock(MapVersionService::class);
        $versions->method('getVersionAt')->willReturn($version);

        $payload = (new ReplayService($rrd, $versions))->buildPayload(new Map(), self::TS);

        $this->assertSame(3, $payload['version_id']);
        return $payload['links'][(string) $link['id']];
    }

    public function test_uses_source_port_and_max_direction_for_utilization(): void
    {
        $data = $this->replayLink(
            ['id' => 1, 'port_id_a' => 10, 'port_id_b' => 20, 'bandwidth_bps' => 1000],
            [10 => ['in' => 300, 'out' => 100], 20 => ['in' => 900, 'out' => 900]]
        );

        $this->assertSame(300, $data['in_bps']);
        $this->assertSame(100, $data['out_bps']);
        $this->assertEquals(30.0, $data['pct']);
        $this->assertSame(1000, $data['bandwidth_bps']);
    }

    public function test_reverses_directions_when_only_port_b_is_mapped(): void
    {
        $data = $this->replayLink(
            ['id' => 2, 'port_id_a' => null, 'port_id_b' => 20, 'bandwidth_bps' => 1000],
            [20 => ['in' => 400, 'out' => 50]]
        );

        $this->assertSame(50, $data['in_bps']);
        $this->assertSame(400, $data['out_bps']);
        $this->assertEquals(40.0, $data['pct']);
    }

    public function test_falls_back_to_slowest_port_speed(): void
    {
        $data = $this->replayLink(
            ['id' => 3, 'port_id_a' => 10, 'port_id_b' => 20, 'bandwidth_bps' => null],
            [10 => ['in' => 500, 'out' => 0]],
            [10 => 10_000, 20 => 1_000]
        );

        $this->assertSame(1000, $data['bandwidth_bps']);
        $this->assertEquals(50.0, $data['pct']);
    }

    public function test_unknown_bandwidth_reports_no_percentage(): void
    {
        $data = $this->replayLink(
            ['id' => 4, 'port_id_a' => 10, 'bandwidth_bps' => null],
            [10 => ['in' => 500, 'out' => 500]]
        );

        $this->assertNull($data['pct']);
        $this->assertNull($data['bandwidth_bps']);
    }
}
