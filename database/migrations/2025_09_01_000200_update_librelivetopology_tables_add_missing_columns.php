<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // llt_maps: add commonly missing columns when upgrading from early installs
        if (Schema::hasTable('llt_maps')) {
            Schema::table('llt_maps', function (Blueprint $t) {
                if (!Schema::hasColumn('llt_maps', 'title')) {
                    $t->string('title')->nullable()->after('name');
                }
                if (!Schema::hasColumn('llt_maps', 'description')) {
                    $t->text('description')->nullable()->after('title');
                }
                if (!Schema::hasColumn('llt_maps', 'width')) {
                    $t->unsignedInteger('width')->default(800);
                }
                if (!Schema::hasColumn('llt_maps', 'height')) {
                    $t->unsignedInteger('height')->default(600);
                }
                if (!Schema::hasColumn('llt_maps', 'options')) {
                    $t->json('options')->nullable();
                }
            });
        }

        // llt_nodes: ensure device_id/meta exist
        if (Schema::hasTable('llt_nodes')) {
            Schema::table('llt_nodes', function (Blueprint $t) {
                if (!Schema::hasColumn('llt_nodes', 'device_id')) {
                    $t->unsignedBigInteger('device_id')->nullable();
                }
                if (!Schema::hasColumn('llt_nodes', 'meta')) {
                    $t->json('meta')->nullable();
                }
            });
        }

        // llt_links: ensure optional columns exist
        if (Schema::hasTable('llt_links')) {
            Schema::table('llt_links', function (Blueprint $t) {
                if (!Schema::hasColumn('llt_links', 'port_id_a')) {
                    $t->unsignedBigInteger('port_id_a')->nullable();
                }
                if (!Schema::hasColumn('llt_links', 'port_id_b')) {
                    $t->unsignedBigInteger('port_id_b')->nullable();
                }
                if (!Schema::hasColumn('llt_links', 'bandwidth_bps')) {
                    $t->unsignedBigInteger('bandwidth_bps')->nullable();
                }
                if (!Schema::hasColumn('llt_links', 'style')) {
                    $t->json('style')->nullable();
                }
            });
        }
    }

    public function down()
    {
        // No-op: conditionally added columns are safe to keep.
        // If needed, they can be dropped manually on downgrade.
    }
};
