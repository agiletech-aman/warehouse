<?php

namespace App\Workstations\Models;

use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $table = 'wsm_releases';
    protected $guarded = [];
    protected $casts = ['is_latest' => 'boolean', 'yanked' => 'boolean'];

}
