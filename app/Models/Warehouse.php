<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'frs_id',
        'nms_id',
        'region_frs_id',
        'region_nms_id',
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
            $region = null;

            if ($warehouse->region_frs_id) {
                $region = Region::where('frs_id', $warehouse->region_frs_id)->first();
            } elseif ($warehouse->region_nms_id) {
                $region = Region::where('nms_id', $warehouse->region_nms_id)->first();
            } elseif ($warehouse->region_id) {
                $region = Region::find($warehouse->region_id);
            }

            if ($region) {
                $warehouse->region_id = $region->id;
                $warehouse->region_frs_id = $region->frs_id;
                $warehouse->region_nms_id = $region->nms_id;
            }
        });

        static::creating(function (Warehouse $warehouse) {
            $warehouse->frs_id ??= (string) Str::uuid7();
        });
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_frs_id', 'frs_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function scopeActiveInLast24Hours(Builder $query): Builder
    {
        $activeSince = now()->subDay();

        return $query->where(function (Builder $query) use ($activeSince) {
            $query->whereExists(function ($readingQuery) use ($activeSince) {
                $readingQuery->selectRaw('1')
                    ->from('readings')
                    ->whereNull('readings.deleted_at')
                    ->where('readings.recorded_at', '>=', $activeSince)
                    ->whereColumn('readings.warehouse_code', 'warehouses.warehouse_code');
            })->orWhereExists(function ($readingQuery) use ($activeSince) {
                $readingQuery->selectRaw('1')
                    ->from('readings')
                    ->whereNull('readings.deleted_at')
                    ->where('readings.recorded_at', '>=', $activeSince)
                    ->whereColumn('readings.warehouse', 'warehouses.warehouse_name');
            });
        });
    }
}
