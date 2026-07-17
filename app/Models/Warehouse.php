<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'region_uuid',
        'region_id',
        'warehouse_code',
        'warehouse_name',
        'manager_name',
        'manager_email',
        'password',
        'manager_phone',
        'address',
        'city',
        'state',
        'country',
        'latitude',
        'longitude',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Warehouse $warehouse) {
            if ($warehouse->region_uuid) {
                $regionId = Region::where('uuid', $warehouse->region_uuid)->value('id');

                if ($regionId !== null) {
                    $warehouse->region_id = $regionId;
                }
            } elseif ($warehouse->region_id) {
                $warehouse->region_uuid = Region::whereKey($warehouse->region_id)->value('uuid');
            }
        });

        static::creating(function (Warehouse $warehouse) {
            $warehouse->uuid ??= (string) Str::uuid7();
        });
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_uuid', 'uuid');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
