<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reading extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'sensor_device_id',

        // FK optional
        'device_id',
        'device_name',
        'device_type',
        'device_ip',

        'unit',
        'port',

        'region',
        'region_code',

        'warehouse',
        'warehouse_code',

        'godown',
        'compartment',

        'reading_value',
        'level',
        'status',

        'recorded_at'
    ];



    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}

