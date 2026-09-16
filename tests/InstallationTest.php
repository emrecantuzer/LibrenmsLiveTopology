<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use LibreNMS\Plugins\LibreLiveTopology\LibreLiveTopology;
use PHPUnit\Framework\TestCase;

class InstallationTest extends TestCase
{
    public function testPluginCanBeInstantiated()
    {
        $plugin = new LibreLiveTopology();
        $this->assertInstanceOf(LibreLiveTopology::class, $plugin);
    }

    public function testPluginHasRequiredMethods()
    {
        $plugin = new LibreLiveTopology();

        $this->assertTrue(method_exists($plugin, 'activate'));
        $this->assertTrue(method_exists($plugin, 'deactivate'));
        $this->assertTrue(method_exists($plugin, 'uninstall'));
        $this->assertTrue(method_exists($plugin, 'getVersion'));
        $this->assertTrue(method_exists($plugin, 'getInfo'));
    }

    public function testPluginInfoIsCorrect()
    {
        $plugin = new LibreLiveTopology();
        $info = $plugin->getInfo();

        $this->assertEquals('LibreLiveTopology', $info['name']);
        $this->assertEquals('Modern interactive network librelivetopology for LibreNMS', $info['description']);
        $this->assertNotEmpty($info['version']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $info['version']);
        $this->assertEquals('LibreLiveTopology contributors', $info['author']);
    }

    public function testRequirementsCheckMethodExists()
    {
        $plugin = new LibreLiveTopology();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($plugin);
        $method = $reflection->getMethod('checkRequirements');
        $method->setAccessible(true);

        $requirements = $method->invoke($plugin);
        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('php', $requirements);
        $this->assertArrayHasKey('gd', $requirements);
    }

    public function testDefaultConfigStructure()
    {
        $plugin = new LibreLiveTopology();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($plugin);
        $method = $reflection->getMethod('getDefaultConfig');
        $method->setAccessible(true);

        $config = $method->invoke($plugin);
        $this->assertIsArray($config);
        $this->assertArrayHasKey('default_width', $config);
        $this->assertArrayHasKey('default_height', $config);
        $this->assertArrayHasKey('colors', $config);
        $this->assertArrayHasKey('rendering', $config);
    }
}
