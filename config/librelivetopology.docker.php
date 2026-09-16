<?php
/**
 * LibreLiveTopology Docker Configuration
 *
 * This configuration file is optimized for Docker container environments.
 * Copy this to config/librelivetopology.php when using Docker.
 */

return [
    // Docker mode enabled
    'docker_mode' => true,

    // Basic map settings
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300, // 5 minutes

    // Utilization thresholds (%)
    'thresholds' => [50, 80, 95],
    'scale' => 'bits',

    // RRD file location (adjust for your container)
    'rrd_base' => env('LIBRENMS_RRD_BASE', '/opt/librenms/rrd'),
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'cache_ttl' => 300,

    // Status colors
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

    // Rendering settings
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],
    'link_style' => 'curved',
    'show_bandwidth' => true,
    'show_node_metrics' => true,

    // Security settings
    'security' => [
        'allow_embed' => true,
        'embed_domains' => ['localhost', '*.yourdomain.com'],
        'max_image_size' => 2048, // KB
    ],

    // Editor settings
    'editor' => [
        'grid_size' => 20,
        'snap_to_grid' => true,
    ],

    // Docker-specific settings
    'log_to_stdout' => env('LOG_TO_STDOUT', true),
    'log_file' => env('LIBRELIVETOPOLOGY_LOG', '/dev/stdout'),
    'output_path' => env('LIBRELIVETOPOLOGY_OUTPUT', '/opt/librenms/html/plugins/LibreLiveTopology/output'),

    // Database connection (use container networking)
    'database' => [
        'host' => env('DB_HOST', 'db'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'librenms'),
        'username' => env('DB_USERNAME', 'librenms'),
        'password' => env('DB_PASSWORD'),
    ],
];
