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
        'alert_type',
        'alert_value',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function reading()
    {
        return $this->belongsTo(Reading::class);
    }
}
