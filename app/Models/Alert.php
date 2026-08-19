<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alert extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'device_id',
        'reading_id',

        // legacy columns
        'alert_type',
        'alert_value',

        // throttling columns
        'type',
        'message',
        'last_email_at',
        'active',
    ];


    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_code');
    }

    public function reading()
    {
        return $this->belongsTo(Reading::class);
    }
}
