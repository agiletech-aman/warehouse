<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Region extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'region_code',
        'region_name',
        'status',
        'manager_name',
        'manager_phone',
        'manager_email',
        'password',
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
        static::creating(function (Region $region) {
            $region->uuid ??= (string) Str::uuid7();
        });
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'region_uuid', 'uuid');
    }
}
