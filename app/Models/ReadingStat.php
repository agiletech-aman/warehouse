<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-row table with the counts shown on the readings page; see readings:refresh-stats. */
class ReadingStat extends Model
{
    protected $fillable = ['total', 'normal', 'severe', 'critical'];
}
