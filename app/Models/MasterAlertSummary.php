<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterAlertSummary extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'machine_id',
        'total_iot_devices',
        'online_co2',
        'offline_co2',
        'online_ph3',
        'offline_ph3',
        'normal_co2',
        'severe_co2',
        'critical_co2',
        'normal_ph3',
        'severe_ph3',
        'critical_ph3',
        'shad_name',
        'column_name',
        'location_name',
        'state',
        'city',
        'pin_code',
        'snapshot_time',
    ];

    protected function casts(): array
    {
        return [
            'total_iot_devices' => 'integer',
            'online_co2' => 'integer',
            'offline_co2' => 'integer',
            'online_ph3' => 'integer',
            'offline_ph3' => 'integer',
            'normal_co2' => 'integer',
            'severe_co2' => 'integer',
            'critical_co2' => 'integer',
            'normal_ph3' => 'integer',
            'severe_ph3' => 'integer',
            'critical_ph3' => 'integer',
            'snapshot_time' => 'datetime',
        ];
    }
}
