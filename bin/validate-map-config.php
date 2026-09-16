#!/usr/bin/env php
<?php

declare(strict_types=1);

$path = $argv[1] ?? null;
if ($path === null || !is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "Map config is missing or unreadable: " . ($path ?? '(not provided)') . PHP_EOL);
    exit(2);
}

$lines = file($path, FILE_IGNORE_NEW_LINES);
$section = null;
$global = [];
$nodes = [];
$links = [];
$errors = [];

foreach ($lines ?: [] as $number => $rawLine) {
    $line = trim($rawLine);
    if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
        continue;
    }
    if (preg_match('/^\[(global|node:[A-Za-z0-9_.-]+|link:[A-Za-z0-9_.-]+)]$/', $line, $match)) {
        $section = $match[1];
        if (str_starts_with($section, 'node:')) $nodes[substr($section, 5)] = [];
        if (str_starts_with($section, 'link:')) $links[substr($section, 5)] = [];
        continue;
    }
    if ($section === null) {
        $errors[] = 'Line ' . ($number + 1) . ': value appears before a section';
        continue;
    }
    [$key, $value] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, '');
    if ($key === '' || $value === '') {
        $errors[] = 'Line ' . ($number + 1) . ': expected "key value"';
        continue;
    }
    if ($section === 'global') $global[$key] = trim($value, '"');
    if (str_starts_with($section, 'node:')) $nodes[substr($section, 5)][$key] = trim($value, '"');
    if (str_starts_with($section, 'link:')) $links[substr($section, 5)][$key] = trim($value, '"');
}

foreach (['width', 'height'] as $dimension) {
    $value = filter_var($global[$dimension] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < 100 || $value > 4096) {
        $errors[] = "global.{$dimension} must be an integer between 100 and 4096";
    }
}
if (isset($global['background_color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $global['background_color'])) {
    $errors[] = 'global.background_color must be a six-digit hex color';
}
foreach ($nodes as $name => $node) {
    foreach (['x', 'y'] as $coordinate) {
        $value = filter_var($node[$coordinate] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0 || $value > 10000) {
            $errors[] = "node:{$name}.{$coordinate} must be an integer between 0 and 10000";
        }
    }
}
foreach ($links as $name => $link) {
    $endpoints = preg_split('/\s+/', $link['nodes'] ?? '') ?: [];
    if (count($endpoints) !== 2 || !isset($nodes[$endpoints[0]], $nodes[$endpoints[1]])) {
        $errors[] = "link:{$name} must reference two declared nodes";
    }
}

if ($errors !== []) {
    foreach ($errors as $error) fwrite(STDERR, "ERROR: {$error}" . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Map config valid: {$path} (" . count($nodes) . ' nodes, ' . count($links) . " links)" . PHP_EOL);
