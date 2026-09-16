<?php

namespace LibreNMS\Plugins\LibreLiveTopology;

class LibreLiveTopology
{
    public $name = 'LibreLiveTopology';
    public $description = 'Modern interactive network librelivetopology for LibreNMS';
    public $architecture = 'hook-based';
    public $librenms_version = '24.x+';

    public function __construct()
    {
        // Plugin initialization is handled by LibreNMS hooks
        // See: app/Plugins/LibreLiveTopology/ directory
    }

    public function activate()
    {
        // Activation logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function deactivate()
    {
        // Deactivation logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function uninstall()
    {
        // Cleanup logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function getVersion()
    {
        $versionFile = dirname(__DIR__) . '/VERSION';
        if (is_readable($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);
        return $composer['version'] ?? '0.0.0';
    }

    public function getInfo()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->getVersion(),
            'author' => 'LibreLiveTopology contributors',
            'architecture' => $this->architecture,
            'librenms_version' => $this->librenms_version,
            'hooks' => [
                'menu' => 'app/Plugins/LibreLiveTopology/Menu.php',
                'page' => 'app/Plugins/LibreLiveTopology/Page.php',
                'settings' => 'app/Plugins/LibreLiveTopology/Settings.php'
            ],
            'note' => 'This plugin uses LibreNMS 24.x hook-based architecture. ' .
                      'Functionality is implemented via hooks, not this bootstrap file.'
        ];
    }

    /**
     * Get hook information for debugging
     */
    public function getHooksInfo()
    {
        return [
            'menu_hook' => 'Adds LibreLiveTopology to LibreNMS navigation menu',
            'page_hook' => 'Provides main librelivetopology interface and editor',
            'settings_hook' => 'Adds librelivetopology configuration to LibreNMS settings',
            'location' => 'app/Plugins/LibreLiveTopology/',
            'architecture' => 'Hook-based (LibreNMS 24.x+)'
        ];
    }

    public function checkRequirements()
    {
        $requirements = [
            'php' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'gd' => extension_loaded('gd'),
            'json' => extension_loaded('json'),
            'pdo' => extension_loaded('pdo'),
            'mbstring' => extension_loaded('mbstring'),
        ];

        return $requirements;
    }

    public function getDefaultConfig()
    {
        return [
            'default_width' => 800,
            'default_height' => 600,
            'poll_interval' => 300,
            'thresholds' => [50, 80, 95],
            'scale' => 'bits',
            'rrd_base' => '/opt/librenms/rrd',
            'rrdcached' => ['socket' => null],
            'cache_ttl' => 300,
            'enable_sse' => true,
            'client_refresh' => 60,
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
            'link_style' => 'curved',
            'show_bandwidth' => true,
            'show_node_metrics' => true,
            'security' => [
                'allow_embed' => true,
                'embed_domains' => ['localhost', '*.yourdomain.com'],
                'max_image_size' => 2048,
            ],
            'editor' => [
                'grid_size' => 20,
                'snap_to_grid' => true,
            ],
        ];
    }
}
