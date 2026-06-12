<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'region_id',
        'warehouse_code',
        'warehouse_name',
        'manager_name',
        'manager_email',
        'manager_phone',
        'address',
        'city',
        'state',
        'country',
        'latitude',
        'longitude',
        'status'
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}