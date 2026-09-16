<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('llt_maps')) {
            Schema::create('llt_maps', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->string('title')->nullable();
                $t->text('description')->nullable();
                $t->unsignedInteger('width')->default(800);
                $t->unsignedInteger('height')->default(600);
                $t->json('options')->nullable(); // bg, thresholds, scale
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('llt_nodes')) {
            Schema::create('llt_nodes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('map_id')->constrained('llt_maps')->onDelete('cascade');
                $t->string('label');
                $t->float('x');
                $t->float('y');
                $t->unsignedBigInteger('device_id')->nullable(); // LibreNMS device id
                $t->json('meta')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('llt_links')) {
            Schema::create('llt_links', function (Blueprint $t) {
                $t->id();
                $t->foreignId('map_id')->constrained('llt_maps')->onDelete('cascade');
                $t->foreignId('src_node_id')->constrained('llt_nodes');
                $t->foreignId('dst_node_id')->constrained('llt_nodes');
                $t->unsignedBigInteger('port_id_a')->nullable();
                $t->unsignedBigInteger('port_id_b')->nullable();
                $t->unsignedBigInteger('bandwidth_bps')->nullable();
                $t->json('style')->nullable(); // stroke, width, label options
                $t->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('llt_links');
        Schema::dropIfExists('llt_nodes');
        Schema::dropIfExists('llt_maps');
    }
};
