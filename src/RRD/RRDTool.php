<?php

// lib/RRD/RRDTool.php
namespace LibreNMS\Plugins\LibreLiveTopology\RRD;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RRDTool
{
    private $rrdtoolPath;

    public function __construct()
    {
        $this->rrdtoolPath = config('librelivetopology.rrdtool_path', '/usr/bin/rrdtool');

        if (!file_exists($this->rrdtoolPath)) {
            $commonPaths = [
                '/usr/bin/rrdtool',
                '/usr/local/bin/rrdtool',
                '/opt/rrdtool/bin/rrdtool',
                'rrdtool'
            ];

            foreach ($commonPaths as $path) {
                if (file_exists($path) || $this->commandExists($path)) {
                    $this->rrdtoolPath = $path;
                    break;
                }
            }
        }
    }

    private function commandExists($command)
    {
        $process = new Process(['which', $command]);
        $process->run();

        return $process->isSuccessful();
    }

    private function normalizePeriod(string $period): string
    {
        if (preg_match('/^(\d+)m$/i', $period, $matches)) {
            return $matches[1] . ' minutes';
        }

        return $period;
    }

    public function fetch($rrdPath, $metric, $period = '1h')
    {
        if (!file_exists($rrdPath)) {
            return [];
        }

        $start = strtotime('-' . $this->normalizePeriod($period));
        $end = time();

        $command = [
            $this->rrdtoolPath,
            'fetch',
            $rrdPath,
            'AVERAGE',
            '-s',
            (string)$start,
            '-e',
            (string)$end,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $output = $process->getOutput();
        return $this->parseRRDOutput($output, $metric);
    }

    private function parseRRDOutput($output, $metric)
    {
        $lines = explode("\n", trim($output));
        $data = [];
        $headerParsed = false;
        $metricIndex = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // INERRORS/OUTERRORS are valid datasource names, not error messages.
            if (preg_match('/^(?:ERROR(?:\s|:)|rrdtool\b)/i', $line)) {
                continue;
            }

            if (!$headerParsed && strpos($line, ':') === false) {
                $headers = preg_split('/\s+/', $line);
                $metricIndex = $this->getMetricIndex($metric, $headers);
                $headerParsed = true;
                continue;
            }

            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                list($timestamp, $values) = $parts;
                $timestamp = trim($timestamp);
                $values = trim($values);

                if (!is_numeric($timestamp)) {
                    continue;
                }

                $valueArray = preg_split('/\s+/', $values);
                $value = $metricIndex !== null && isset($valueArray[$metricIndex]) ? trim($valueArray[$metricIndex]) : null;

                if (
                    $value !== null &&
                    $value !== 'nan' &&
                    $value !== 'NAN' &&
                    $value !== 'U' &&
                    $value !== '-nan' &&
                    is_numeric($value)
                ) {
                    $data[] = [
                        'timestamp' => (int)$timestamp,
                        'value' => (float)$value
                    ];
                }
            }
        }

        return $data;
    }

    private function getMetricIndex($metric, $headers)
    {
        $metricMap = [
            'traffic_in' => ['traffic_in', 'INOCTETS'],
            'traffic_out' => ['traffic_out', 'OUTOCTETS'],
            'packets_in' => ['packets_in', 'INPKTS'],
            'packets_out' => ['packets_out', 'OUTPKTS'],
            'errors_in' => ['errors_in', 'INERRORS'],
            'errors_out' => ['errors_out', 'OUTERRORS'],
        ];

        $possibleNames = $metricMap[$metric] ?? [$metric];

        foreach ($possibleNames as $name) {
            $index = array_search(strtoupper($name), array_map('strtoupper', $headers), true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    public function getLastValue($rrdPath, $metric)
    {
        $data = $this->fetch($rrdPath, $metric, '5m');

        if (empty($data)) {
            return null;
        }

        $lastEntry = end($data);
        return $lastEntry['value'];
    }

    public function getValueAt(string $rrdPath, string $metric, int $timestamp): ?float
    {
        if (!file_exists($rrdPath)) {
            return null;
        }

        $process = new Process([
            $this->rrdtoolPath,
            'fetch',
            $rrdPath,
            'AVERAGE',
            '-s', (string) ($timestamp - 600),
            '-e', (string) ($timestamp + 60),
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $values = $this->parseRRDOutput($process->getOutput(), $metric);
        if (empty($values)) {
            return null;
        }

        usort($values, fn ($a, $b) => abs($a['timestamp'] - $timestamp) <=> abs($b['timestamp'] - $timestamp));
        return (float) $values[0]['value'];
    }

    /**
     * Fetch the last row of every column from an RRD file in a single rrdtool invocation.
     *
     * Returns an associative array mapping canonical metric names (traffic_in,
     * traffic_out, …) to their last numeric value, or null when unavailable.
     *
     * @return array<string, float|null>
     */
    public function getLastValues($rrdPath): array
    {
        $row = $this->fetchLastRow($rrdPath, '5m');

        if (empty($row)) {
            return $this->getLastValuesFromSeries($rrdPath);
        }

        $result = [];
        $normalizedRow = [];
        foreach ($row as $name => $value) {
            $normalizedRow[strtoupper((string) $name)] = $value;
        }
        foreach ($this->allKnownMetrics() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $key = strtoupper($alias);
                if (array_key_exists($key, $normalizedRow) && $this->isValidValue($normalizedRow[$key])) {
                    $result[$canonical] = (float) $normalizedRow[$key];
                    continue 2;
                }
            }
        }

        if (empty($result)) {
            return $this->getLastValuesFromSeries($rrdPath);
        }

        return $result;
    }

    /**
     * Fallback for RRD output variants where the last-row parser cannot map
     * the datasource header. The regular series parser already handles the
     * LibreNMS INOCTETS/OUTOCTETS layout.
     */
    private function getLastValuesFromSeries($rrdPath): array
    {
        $result = [];
        foreach (['traffic_in', 'traffic_out'] as $metric) {
            $series = $this->fetch($rrdPath, $metric, '10m');
            if (!empty($series)) {
                $last = end($series);
                $result[$metric] = (float) $last['value'];
            }
        }

        return $result;
    }

    /**
     * Run rrdtool fetch once and return the last data row keyed by column header.
     *
     * @return array<string, string|null>
     */
    private function fetchLastRow($rrdPath, $period = '1h')
    {
        if (!file_exists($rrdPath)) {
            return [];
        }

        $start = strtotime('-5 minutes');
        $end = time();

        $command = [
            $this->rrdtoolPath,
            'fetch',
            $rrdPath,
            'AVERAGE',
            '-s',
            (string)$start,
            '-e',
            (string)$end,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        return $this->parseLastRow($process->getOutput());
    }

    /**
     * Parse rrdtool fetch output and return the last valid data row keyed by header.
     *
     * @return array<string, string|null>
     */
    private function parseLastRow($output): array
    {
        $lines = explode("\n", trim($output));
        $headers = [];
        $headerParsed = false;
        $lastRow = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // INERRORS/OUTERRORS are valid datasource names, not error messages.
            if (preg_match('/^(?:ERROR(?:\s|:)|rrdtool\b)/i', $line)) {
                continue;
            }

            if (!$headerParsed && strpos($line, ':') === false) {
                $headers = preg_split('/\s+/', $line);
                $headerParsed = true;
                continue;
            }

            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                [$timestamp, $values] = $parts;
                $timestamp = trim($timestamp);
                $values = trim($values);

                if (!is_numeric($timestamp)) {
                    continue;
                }

                $valueArray = preg_split('/\s+/', $values);
                $row = [];
                $hasNumericValue = false;
                foreach ($headers as $i => $name) {
                    $row[$name] = isset($valueArray[$i]) ? trim($valueArray[$i]) : null;
                    if ($this->isValidValue($row[$name])) {
                        $hasNumericValue = true;
                    }
                }
                if ($hasNumericValue) {
                    $lastRow = $row;
                }
            }
        }

        return $lastRow;
    }

    /**
     * @return array<string, array<string>>
     */
    private function allKnownMetrics(): array
    {
        return [
            'traffic_in' => ['traffic_in', 'INOCTETS'],
            'traffic_out' => ['traffic_out', 'OUTOCTETS'],
            'packets_in' => ['packets_in', 'INPKTS'],
            'packets_out' => ['packets_out', 'OUTPKTS'],
            'errors_in' => ['errors_in', 'INERRORS'],
            'errors_out' => ['errors_out', 'OUTERRORS'],
        ];
    }

    private function isValidValue($value): bool
    {
        return $value !== null
            && $value !== 'nan'
            && $value !== 'NAN'
            && $value !== 'U'
            && $value !== '-nan'
            && is_numeric($value);
    }

    public function getAverageValue($rrdPath, $metric, $period = '1h')
    {
        $data = $this->fetch($rrdPath, $metric, $period);

        if (empty($data)) {
            return null;
        }

        $sum = 0;
        $count = 0;

        foreach ($data as $entry) {
            if ($entry['value'] > 0) {
                $sum += $entry['value'];
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : null;
    }
}
