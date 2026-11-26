<?php

namespace App\Workstations\Models;

use Illuminate\Database\Eloquent\Model;

class Workstation extends Model
{
    protected $table = 'wsm_workstations';
    protected $guarded = [];
    protected $casts = ['last_seen_at' => 'datetime', 'auto_update' => 'boolean'];

}
