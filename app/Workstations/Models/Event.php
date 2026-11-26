<?php

namespace App\Workstations\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'wsm_events';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
}
