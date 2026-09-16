<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Models;

use Illuminate\Database\Eloquent\Model;

class MapTemplate extends Model
{
    protected $table = 'llt_map_templates';

    protected $fillable = [
        'name',
        'title',
        'description',
        'width',
        'height',
        'config',
        'icon',
        'category',
        'is_built_in',
    ];

    protected $casts = [
        'config' => 'array',
        'is_built_in' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

}
