<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\LibreLiveTopology\Services\AutoDiscoveryService;

class AutoDiscoveryServiceTest extends TestCase
{
    private AutoDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutoDiscoveryService();
    }

    public function test_validate_params_with_empty_input(): void
    {
        $result = $this->service->validateDiscoveryParams([]);

        $this->assertEquals(0, $result['minDegree']);
        $this->assertEmpty($result['osFilter']);
    }

    public function test_validate_params_with_min_degree(): void
    {
        $result = $this->service->validateDiscoveryParams(['min_degree' => '3']);

        $this->assertEquals(3, $result['minDegree']);
    }

    public function test_validate_params_negative_degree_clamped_to_zero(): void
    {
        $result = $this->service->validateDiscoveryParams(['min_degree' => '-5']);

        $this->assertEquals(0, $result['minDegree']);
    }

    public function test_validate_params_with_single_os_filter(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => 'linux']);

        $this->assertEquals(['linux'], $result['osFilter']);
    }

    public function test_validate_params_with_multiple_os_filters(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => 'linux,ios,junos']);

        $this->assertEquals(['linux', 'ios', 'junos'], $result['osFilter']);
    }

    public function test_validate_params_trims_whitespace_from_os_filters(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => ' linux , ios , junos ']);

        $this->assertEquals(['linux', 'ios', 'junos'], $result['osFilter']);
    }

    public function test_validate_params_filters_empty_strings(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => ',linux,,ios,']);

        $this->assertNotContains('', $result['osFilter']);
        $this->assertSame(['linux', 'ios'], $result['osFilter']);
    }

    public function test_topology_preserves_parallel_circuits_and_ignores_incomplete_duplicate_observations(): void
    {
        $ports = [
            10 => [['port_id' => 100, 'ifIndex' => 1], ['port_id' => 101, 'ifIndex' => 2]],
            20 => [['port_id' => 200, 'ifIndex' => 1], ['port_id' => 201, 'ifIndex' => 2]],
        ];
        $rows = [
            ['local_device_id' => 10, 'remote_device_id' => 20, 'local_port_id' => 100, 'remote_port_id' => 0],
            ['local_device_id' => 10, 'remote_device_id' => 20, 'local_port_id' => 0, 'remote_port_id' => 0],
            ['local_device_id' => 10, 'remote_device_id' => 20, 'local_port_id' => 100, 'remote_port_id' => 200],
            ['local_device_id' => 20, 'remote_device_id' => 10, 'local_port_id' => 200, 'remote_port_id' => 100],
            ['local_device_id' => 20, 'remote_device_id' => 10, 'local_port_id' => 201, 'remote_port_id' => 101],
        ];
        $mapping = [10 => 1, 20 => 2];

        $links = $this->invokePrivate('buildLinksFromTopology', $rows, $mapping, $ports);

        $this->assertCount(2, $links);
        $this->assertSame([100, 101], array_column($links, 'port_a'));
        $this->assertSame([200, 201], array_column($links, 'port_b'));
        $this->assertSame($links, $this->invokePrivate('buildLinksFromTopology', array_reverse($rows), $mapping, $ports));
    }

    public function test_conflicting_remote_ports_do_not_merge_and_unknown_endpoints_are_not_guessed(): void
    {
        $ports = [
            10 => [['port_id' => 100, 'ifIndex' => 1], ['port_id' => 101, 'ifIndex' => 2]],
            20 => [['port_id' => 200, 'ifIndex' => 1], ['port_id' => 201, 'ifIndex' => 2]],
        ];
        $rows = [
            ['local_device_id' => 10, 'remote_device_id' => 20, 'local_port_id' => 100, 'remote_port_id' => 200],
            ['local_device_id' => 10, 'remote_device_id' => 20, 'local_port_id' => 100, 'remote_port_id' => 201],
            ['local_device_id' => 10, 'remote_device_id' => 20, 'local_port_id' => 101, 'remote_port_id' => 0],
            ['local_device_id' => 10, 'remote_device_id' => 10, 'local_port_id' => 100, 'remote_port_id' => 101],
            ['local_device_id' => 10, 'remote_device_id' => 99, 'local_port_id' => 100, 'remote_port_id' => 0],
        ];

        $links = $this->invokePrivate('buildLinksFromTopology', $rows, [10 => 1, 20 => 2], $ports);

        $this->assertCount(3, $links);
        $this->assertSame([200, 201, null], array_column($links, 'port_b'));
    }

    public function test_port_resolution_is_device_scoped_and_does_not_guess_missing_ids_from_ifindex(): void
    {
        $ports = [
            10 => [['port_id' => 100, 'ifIndex' => 200]],
            20 => [['port_id' => 200, 'ifIndex' => 1]],
        ];

        $this->assertSame(100, $this->invokePrivate('resolveTopologyPort', 10, '100', $ports));
        $this->assertSame(200, $this->invokePrivate('resolveTopologyPort', 20, 200, $ports));
        $this->assertNull($this->invokePrivate('resolveTopologyPort', 10, 200, $ports));
        $this->assertNull($this->invokePrivate('resolveTopologyPort', 20, 1, $ports));
        $this->assertNull($this->invokePrivate('resolveTopologyPort', 10, '100-invalid', $ports));
        $this->assertNull($this->invokePrivate('resolveTopologyPort', 10, -1, $ports));
    }

    private function invokePrivate(string $name, ...$args)
    {
        $method = (new \ReflectionClass($this->service))->getMethod($name);
        $method->setAccessible(true);
        return $method->invoke($this->service, ...$args);
    }

    public function test_validate_params_with_all_options(): void
    {
        $result = $this->service->validateDiscoveryParams([
            'min_degree' => '2',
            'os' => 'linux,ios',
        ]);

        $this->assertEquals(2, $result['minDegree']);
        $this->assertCount(2, $result['osFilter']);
    }

    public function test_create_link_key_is_deterministic(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createLinkKey');
        $method->setAccessible(true);

        // Same pair should give same key regardless of order
        $key1 = $method->invoke($this->service, 5, 10);
        $key2 = $method->invoke($this->service, 10, 5);

        $this->assertEquals($key1, $key2);
        $this->assertEquals('5-10', $key1);
    }

    public function test_create_link_key_with_same_device(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createLinkKey');
        $method->setAccessible(true);

        $key = $method->invoke($this->service, 7, 7);
        $this->assertEquals('7-7', $key);
    }
}
