<?php
return [
    'demo_mode' => env('LIBRELIVETOPOLOGY_DEMO_MODE', false), // Enable simulated traffic data
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300,         // seconds for CLI poller default
    'thresholds'    => [50,80,95],  // % utilization thresholds
    'scale'         => 'bits',      // 'bits' or 'bytes'
    'rrd_base'      => '/opt/librenms/rrd',
    'output_dir'    => __DIR__ . '/../output/maps/',
    'thumbnail_dir' => __DIR__ . '/../output/thumbnails/',
    'rrdcached'     => [
        'socket' => null,           // e.g. /var/run/rrdcached.sock
    ],
    'enable_local_rrd' => true,     // Use local RRD files
    'enable_api_fallback' => true,  // Fallback to LibreNMS API
    'cache_ttl' => 300,             // Cache TTL in seconds
    'enable_sse' => true,           // Enable Server-Sent Events for live updates
    'client_refresh' => 60,         // Seconds for client polling fallback
    'snmp' => [
        'enabled' => false,         // Enable SNMP fallback for live data if RRD/API unavailable
        'version' => '2c',          // SNMP version
        'community' => env('LIBRELIVETOPOLOGY_SNMP_COMMUNITY'),
        'timeout' => 1,
        'retries' => 1,
    ],
    'api_token' => env('LIBRENMS_API_TOKEN'),
    'colors' => [
        'node_up' => '#22a06b',
        'node_down' => '#d64545',
        'node_warning' => '#d99a21',
        'node_unknown' => '#718096',
        'link_normal' => '#3b82a0',
        'link_warning' => '#d99a21',
        'link_critical' => '#d64545',
        'background' => '#f4f7fa',
    ],
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],
    'link_style' => 'curved',           // Default via_style: straight | angled | curved
    'show_bandwidth' => true,           // Show bandwidth labels on embed viewer
    'show_percentages' => true,         // Show percentage labels on embed viewer
    'show_node_metrics' => true,        // Show node CPU/mem utilization on embed viewer
    'debug' => env('LIBRELIVETOPOLOGY_DEBUG', false), // Log per-endpoint traffic counters to the application log for diagnosing link utilization
    'security' => [
        'allow_embed' => true,
        'embed_domains' => ['localhost', '*.yourdomain.com'],
        'max_image_size' => 2048, // KB
    ],
    'editor' => [
        'grid_size' => 20,
        'snap_to_grid' => true,
    ],
];
