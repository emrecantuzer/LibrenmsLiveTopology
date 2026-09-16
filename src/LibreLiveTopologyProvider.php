<?php

namespace LibreNMS\Plugins\LibreLiveTopology;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;
use LibreNMS\Plugins\LibreLiveTopology\Console\Commands\CreateMapCommand;
use LibreNMS\Plugins\LibreLiveTopology\Console\Commands\DiscoverCommand;
use LibreNMS\Plugins\LibreLiveTopology\Console\Commands\ExportMapCommand;
use LibreNMS\Plugins\LibreLiveTopology\Console\Commands\ListMapsCommand;
use LibreNMS\Plugins\LibreLiveTopology\Hooks\MenuEntry;
use LibreNMS\Plugins\LibreLiveTopology\Hooks\Settings;

class LibreLiveTopologyProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            CreateMapCommand::class,
            ListMapsCommand::class,
            ExportMapCommand::class,
            DiscoverCommand::class,
        ]);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(PluginManagerInterface $pluginManager): void
    {
        $pluginName = 'LibreLiveTopology';

        // Register hooks with LibreNMS (only MenuEntry and Settings are supported)
        $pluginManager->publishHook($pluginName, MenuEntryHook::class, MenuEntry::class);
        $pluginManager->publishHook($pluginName, SettingsHook::class, Settings::class);

        // Register config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php',
            'librelivetopology'
        );

        // Keep install and health routes available before the plugin is enabled.
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load views with namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', $pluginName);

        if (! $pluginManager->pluginEnabled($pluginName)) {
            return; // if plugin is disabled, hooks and publishable assets are skipped
        }

        // Publish assets if running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('librelivetopology.php'),
            ]);
        }
    }
}
