<?php
// config/settings.php
return [
    'map_dir' => __DIR__ . '/maps/',
    'output_dir' => __DIR__ . '/../output/maps/',
    'thumbnail_dir' => __DIR__ . '/../output/thumbnails/',
    'poll_interval' => 300, // 5 minutes
    'default_width' => 800,
    'default_height' => 600,
    'rrd_path' => '/opt/librenms/rrd',
    'api_token' => env('LIBRENMS_API_TOKEN'),
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'cache_ttl' => 300, // 5 minutes
    'security' => [
        'allow_embed' => true,
        'embed_domains' => ['localhost', '*.yourdomain.com'],
        'max_image_size' => 2048, // KB
    ],
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],
    'colors' => [
        'node_up' => '#22a06b',
        'node_down' => '#d64545',
        'node_unknown' => '#718096',
        'link_normal' => '#3b82a0',
        'link_warning' => '#d99a21',
        'link_critical' => '#d64545',
        'background' => '#f4f7fa',
    ]
];
