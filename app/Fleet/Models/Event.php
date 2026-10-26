<?php

namespace App\Fleet\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'fleet_events';
    protected $guarded = [];
    public $timestamps = false;
}
