<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'device_code',
        'device_name',
        'device_type',
        'model_no',
        'serial_no',
        'mac_address',
        'ip_address',
        'firmware_version',
        'installation_date',
        'last_seen_at',
        'status',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function readings()
    {
        return $this->hasMany(Reading::class);
    }

    public function latestReading()
    {
        return $this->hasOne(Reading::class)->latestOfMany('recorded_at');
    }
}
