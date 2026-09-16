<?php

use Illuminate\Support\Facades\Route;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\RenderController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\MapController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\MapLinkController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\MapNodeController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\MapTemplateController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\MapVersionController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\HealthController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\InstallController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\LookupController;
use LibreNMS\Plugins\LibreLiveTopology\Http\Controllers\PageController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('plugin/LibreLiveTopology/install', [InstallController::class, 'index'])->name('librelivetopology.install');
    Route::post('plugin/LibreLiveTopology/install', [InstallController::class, 'install'])->name('librelivetopology.install.run');

    Route::get('plugin/LibreLiveTopology', [PageController::class, 'index'])->name('librelivetopology.index');
    Route::get('plugin/LibreLiveTopology/editor/{map?}', [PageController::class, 'editor'])->name('librelivetopology.editor');
    Route::get('plugin/LibreLiveTopology/view/{map}', [PageController::class, 'view'])->name('librelivetopology.view');

    Route::get('plugin/LibreLiveTopology/embed/{map}', [RenderController::class, 'embed'])->name('librelivetopology.embed');
    Route::get('plugin/LibreLiveTopology/api/maps/{map}/json', [RenderController::class, 'json'])->name('librelivetopology.json');
    Route::get('plugin/LibreLiveTopology/api/maps/{map}/live', [RenderController::class, 'live'])
        ->middleware('throttle:60,1')->name('librelivetopology.live');
    Route::get('plugin/LibreLiveTopology/api/maps/{map}/replay', [RenderController::class, 'replay'])
        ->middleware('throttle:30,1')->name('librelivetopology.replay');
    Route::get('plugin/LibreLiveTopology/api/maps/{map}/export', [RenderController::class, 'export'])->name('librelivetopology.export');
    Route::post('plugin/LibreLiveTopology/api/import', [RenderController::class, 'import'])->name('librelivetopology.import');
    Route::get('plugin/LibreLiveTopology/api/maps/{map}/sse', [RenderController::class, 'sse'])
        ->middleware('throttle:12,1')->name('librelivetopology.sse');

    Route::get('plugin/LibreLiveTopology/api/devices', [LookupController::class, 'devices'])->name('librelivetopology.devices');
    Route::get('plugin/LibreLiveTopology/api/device/{id}/ports', [LookupController::class, 'ports'])->name('librelivetopology.device.ports');

    Route::post('plugin/LibreLiveTopology/map', [MapController::class, 'create'])->name('librelivetopology.map.create');
    Route::put('plugin/LibreLiveTopology/map/{map}', [MapController::class, 'update'])->name('librelivetopology.map.update');
    Route::delete('plugin/LibreLiveTopology/map/{map}', [MapController::class, 'destroy'])->name('librelivetopology.map.destroy');
    Route::post('plugin/LibreLiveTopology/map/{map}/nodes', [MapNodeController::class, 'store'])->name('librelivetopology.nodes.store');
    Route::post('plugin/LibreLiveTopology/map/{map}/links', [MapLinkController::class, 'store'])->name('librelivetopology.links.store');
    Route::post('plugin/LibreLiveTopology/api/maps/{map}/save', [MapController::class, 'save'])->name('librelivetopology.map.save');
    Route::post('plugin/LibreLiveTopology/map/{map}/autodiscover', [MapController::class, 'autoDiscover'])->name('librelivetopology.map.autodiscover');
    Route::patch('plugin/LibreLiveTopology/map/{map}/node/{node}', [MapNodeController::class, 'update'])->name('librelivetopology.node.update');
    Route::patch('plugin/LibreLiveTopology/map/{map}/link/{link}', [MapLinkController::class, 'update'])->name('librelivetopology.link.update');
    Route::post('plugin/LibreLiveTopology/map/{map}/node', [MapNodeController::class, 'create'])->name('librelivetopology.node.create');
    Route::delete('plugin/LibreLiveTopology/map/{map}/node/{node}', [MapNodeController::class, 'delete'])->name('librelivetopology.node.delete');
    Route::delete('plugin/LibreLiveTopology/map/{map}/link/{link}', [MapLinkController::class, 'delete'])->name('librelivetopology.link.delete');
    Route::post('plugin/LibreLiveTopology/map/{map}/link', [MapLinkController::class, 'create'])->name('librelivetopology.link.create');

    // Map versioning
    Route::get('plugin/LibreLiveTopology/api/maps/{map}/versions', [MapVersionController::class, 'index'])->name('librelivetopology.versions.index');
    Route::post('plugin/LibreLiveTopology/api/maps/{map}/versions', [MapVersionController::class, 'store'])->name('librelivetopology.versions.store');
    Route::get('plugin/LibreLiveTopology/api/versions/{versionId}', [MapVersionController::class, 'show'])->name('librelivetopology.versions.show');
    Route::post('plugin/LibreLiveTopology/api/versions/{versionId}/restore', [MapVersionController::class, 'restore'])->name('librelivetopology.versions.restore');
    Route::get('plugin/LibreLiveTopology/api/versions/{versionId}/compare/{compareId}', [MapVersionController::class, 'compare'])->name('librelivetopology.versions.compare');
    Route::delete('plugin/LibreLiveTopology/api/versions/{versionId}', [MapVersionController::class, 'destroy'])->name('librelivetopology.versions.destroy');
    Route::get('plugin/LibreLiveTopology/api/maps/{map}/versions/export', [MapVersionController::class, 'export'])->name('librelivetopology.versions.export');

    Route::get('plugin/LibreLiveTopology/templates', [MapTemplateController::class, 'index'])->name('librelivetopology.templates.index');
    Route::get('plugin/LibreLiveTopology/templates/{id}', [MapTemplateController::class, 'show'])->name('librelivetopology.templates.show');
    Route::post('plugin/LibreLiveTopology/templates', [MapTemplateController::class, 'store'])->name('librelivetopology.templates.store');
    Route::put('plugin/LibreLiveTopology/templates/{id}', [MapTemplateController::class, 'update'])->name('librelivetopology.templates.update');
    Route::delete('plugin/LibreLiveTopology/templates/{id}', [MapTemplateController::class, 'destroy'])->name('librelivetopology.templates.destroy');
    Route::post('plugin/LibreLiveTopology/templates/{id}/create-map', [MapTemplateController::class, 'createFromTemplate'])->name('librelivetopology.templates.create-map');

    Route::get('plugin/LibreLiveTopology/health/detailed', [HealthController::class, 'detailed'])->name('librelivetopology.health.detailed');
    Route::get('plugin/LibreLiveTopology/health/stats', [HealthController::class, 'stats'])->name('librelivetopology.health.stats');
    Route::get('plugin/LibreLiveTopology/metrics', [HealthController::class, 'metrics'])->name('librelivetopology.metrics');
    Route::get('plugin/LibreLiveTopology/diagnostics', [HealthController::class, 'diagnostics'])->name('librelivetopology.diagnostics');
});

Route::prefix('plugin/LibreLiveTopology')->group(function () {
    Route::get('/health', [HealthController::class, 'check'])->name('librelivetopology.health');
    Route::get('/ready', [HealthController::class, 'ready'])->name('librelivetopology.ready');
    Route::get('/live', [HealthController::class, 'live'])->name('librelivetopology.alive');
});
