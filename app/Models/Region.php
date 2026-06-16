<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'region_code',
        'region_name',
        'status',
        'manager_name',
        'manager_phone',
        'manager_email',
    ];

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }
}

