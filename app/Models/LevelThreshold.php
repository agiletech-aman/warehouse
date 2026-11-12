<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LevelThreshold extends Model
{
    private const CACHE_KEY = 'level_thresholds.map';

    /**
     * Used only when the table is missing or empty (e.g. before migrating).
     */
    public const DEFAULTS = [
        'PH3' => ['normal' => 0.3, 'severe' => 0.75],
        'CO2' => ['normal' => 800, 'severe' => 1700],
    ];

    protected $fillable = ['gas', 'normal_max', 'severe_max'];

    protected $casts = [
        'normal_max' => 'float',
        'severe_max' => 'float',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Thresholds keyed by gas, e.g. ['PH3' => ['normal' => 0.3, 'severe' => 0.75]].
     * This is the single source used by the API, dashboard and level calculation.
     *
     * @return array<string, array{normal: float, severe: float}>
     */
    public static function map(): array
    {
        try {
            $map = Cache::rememberForever(self::CACHE_KEY, function () {
                return static::query()
                    ->orderBy('id')
                    ->get()
                    ->mapWithKeys(fn (self $row) => [
                        strtoupper($row->gas) => [
                            'normal' => (float) $row->normal_max,
                            'severe' => (float) $row->severe_max,
                        ],
                    ])
                    ->all();
            });
        } catch (Throwable) {
            return self::DEFAULTS;
        }

        return $map ?: self::DEFAULTS;
    }
}
