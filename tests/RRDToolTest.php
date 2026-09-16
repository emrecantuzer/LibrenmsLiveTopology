<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use LibreNMS\Plugins\LibreLiveTopology\RRD\RRDTool;
use PHPUnit\Framework\TestCase;

class RRDToolTest extends TestCase
{
    protected RRDTool $rrdTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rrdTool = new RRDTool();
    }

    public function test_series_parser_does_not_copy_rx_when_tx_column_is_missing(): void
    {
        $parser = new \ReflectionMethod($this->rrdTool, 'parseRRDOutput');
        $parser->setAccessible(true);
        $output = "INOCTETS\n1700000000: 1000\n";
        $this->assertSame([], $parser->invoke($this->rrdTool, $output, 'traffic_out'));
        $output = "inoctets outoctets\n1700000000: 1000 250\n";
        $rx = $parser->invoke($this->rrdTool, $output, 'traffic_in');
        $tx = $parser->invoke($this->rrdTool, $output, 'traffic_out');
        $this->assertEquals(1000, $rx[0]['value']);
        $this->assertEquals(250, $tx[0]['value']);
    }

    public function test_real_librenms_error_counter_headers_preserve_direction_columns(): void
    {
        // Real port 7735 sample: INERRORS and OUTERRORS are datasource names,
        // not rrdtool error messages. Skipping this header mirrors RX into TX.
        $output = "INOCTETS OUTOCTETS INERRORS OUTERRORS INUCASTPKTS OUTUCASTPKTS INNUCASTPKTS OUTNUCASTPKTS INDISCARDS OUTDISCARDS INUNKNOWNPROTOS INBROADCASTPKTS OUTBROADCASTPKTS INMULTICASTPKTS OUTMULTICASTPKTS\n"
            . "1789481700: 5.3784438044e+03 2.3314486035e+03 0 0 14.725991059 14.009743505 -nan -nan 0 0 -nan 0.075415566614 0.92765154013 0.45278911450 0.23365852727\n";
        $rowParser = new \ReflectionMethod($this->rrdTool, 'parseLastRow');
        $rowParser->setAccessible(true);
        $row = $rowParser->invoke($this->rrdTool, $output);
        $this->assertEquals(5378.4438044, (float) $row['INOCTETS']);
        $this->assertEquals(2331.4486035, (float) $row['OUTOCTETS']);
        $seriesParser = new \ReflectionMethod($this->rrdTool, 'parseRRDOutput');
        $seriesParser->setAccessible(true);
        $rx = $seriesParser->invoke($this->rrdTool, $output, 'traffic_in');
        $tx = $seriesParser->invoke($this->rrdTool, $output, 'traffic_out');
        $this->assertEquals(43028, (int) round($rx[0]['value'] * 8));
        $this->assertEquals(18652, (int) round($tx[0]['value'] * 8));
        $this->assertSame([], $seriesParser->invoke($this->rrdTool, "1789481700: 100 200\n", 'traffic_out'));
        $this->assertSame([], $rowParser->invoke($this->rrdTool, "ERROR: opening file\n"));
    }

    /** @test */
    public function rrdtool_can_be_instantiated()
    {
        $this->assertInstanceOf(RRDTool::class, $this->rrdTool);
    }

    /** @test */
    public function rrdtool_has_required_methods()
    {
        $this->assertTrue(method_exists($this->rrdTool, 'fetch'));
        $this->assertTrue(method_exists($this->rrdTool, 'getLastValue'));
        $this->assertTrue(method_exists($this->rrdTool, 'getAverageValue'));
        $this->assertTrue(method_exists($this->rrdTool, 'getLastValues'));
    }

    /** @test */
    public function fetch_returns_empty_array_for_nonexistent_file()
    {
        $result = $this->rrdTool->fetch('/nonexistent/file.rrd', 'traffic_in');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function getLastValue_returns_null_for_nonexistent_file()
    {
        $result = $this->rrdTool->getLastValue('/nonexistent/file.rrd', 'traffic_in');
        $this->assertNull($result);
    }

    /** @test */
    public function getAverageValue_returns_null_for_nonexistent_file()
    {
        $result = $this->rrdTool->getAverageValue('/nonexistent/file.rrd', 'traffic_in');
        $this->assertNull($result);
    }

    /** @test */
    public function getLastValues_returns_empty_array_for_nonexistent_file()
    {
        $result = $this->rrdTool->getLastValues('/nonexistent/file.rrd');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function getLastValues_returns_both_traffic_metrics_from_single_invocation()
    {
        // Simulate rrdtool fetch output with both traffic_in and traffic_out columns.
        // Uses Reflection to test the private parseLastRow parser directly.
        $output = "traffic_in traffic_out\n" .
                  "1700000000: 1000 2000\n" .
                  "1700000300: 1500 2500\n";

        $ref = new \ReflectionMethod($this->rrdTool, 'parseLastRow');
        $ref->setAccessible(true);
        $row = $ref->invoke($this->rrdTool, $output);

        $this->assertIsArray($row);
        $this->assertEquals('1500', $row['traffic_in']);
        $this->assertEquals('2500', $row['traffic_out']);
    }

    /** @test */
    public function parseLastRow_keeps_last_numeric_row_when_fetch_ends_with_unknown_values()
    {
        $output = "traffic_in traffic_out\n" .
                  "1700000000: 1000 2000\n" .
                  "1700000300: nan nan\n";

        $ref = new \ReflectionMethod($this->rrdTool, 'parseLastRow');
        $ref->setAccessible(true);
        $row = $ref->invoke($this->rrdTool, $output);

        $this->assertSame('1000', $row['traffic_in']);
        $this->assertSame('2000', $row['traffic_out']);
    }

    // Note: Full RRD testing requires actual RRD files and rrdtool binary
    // These basic tests verify the class structure and error handling
    // Comprehensive testing would require test RRD files and rrdtool installation
}