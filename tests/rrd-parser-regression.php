<?php
// Standalone regression: no LibreNMS, Composer, RRD binary or database required.
declare(strict_types=1);
require $argv[1] ?? __DIR__ . '/../src/RRD/RRDTool.php';
require __DIR__ . '/../src/Services/RrdDataService.php';
$tool = (new ReflectionClass(\LibreNMS\Plugins\LibreLiveTopology\RRD\RRDTool::class))->newInstanceWithoutConstructor();
$rrd = (new ReflectionClass(\LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService::class))->newInstanceWithoutConstructor();
$rowParser = new ReflectionMethod($tool, 'parseLastRow');
$seriesParser = new ReflectionMethod($tool, 'parseRRDOutput');
$toBits = new ReflectionMethod($rrd, 'octetsToBits');
$sample = file_get_contents(__DIR__ . '/fixtures/rrd-port-directions.txt');
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$row = $rowParser->invoke($tool, $sample);
$check(isset($row['INOCTETS'], $row['OUTOCTETS']), 'Real LibreNMS header must not be discarded as an error');
$check($toBits->invoke($rrd, $row['INOCTETS']) === 43028, 'RX must be 43028 bps');
$check($toBits->invoke($rrd, $row['OUTOCTETS']) === 18652, 'TX must be 18652 bps');
$rx = $seriesParser->invoke($tool, $sample, 'traffic_in');
$tx = $seriesParser->invoke($tool, $sample, 'traffic_out');
$check(count($rx) === 1 && count($tx) === 1, 'Both fallback series must contain a sample');
$check($toBits->invoke($rrd, $rx[0]['value']) === 43028, 'Fallback RX must use INOCTETS');
$check($toBits->invoke($rrd, $tx[0]['value']) === 18652, 'Fallback TX must use OUTOCTETS');
$check($seriesParser->invoke($tool, "1789481700: 100 200\n", 'traffic_out') === [], 'Headerless data must not read column zero');
$check($seriesParser->invoke($tool, "INOCTETS\n1789481700: 100\n", 'traffic_out') === [], 'Missing TX must not copy RX');
$check($rowParser->invoke($tool, "ERROR: opening file\n") === [], 'Actual error messages must be ignored');
echo "PASS: real LibreNMS header, independent RX/TX, byte-to-bit conversion, missing/headerless data. RX=43028 bps TX=18652 bps\n";
