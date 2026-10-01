<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FnsDetectionWarehouseLookup
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    public static function forCameraIp(?string $cameraIp): ?string
    {
        $cameraIp = trim((string) $cameraIp);

        if ($cameraIp === '') {
            return null;
        }

        if (array_key_exists($cameraIp, self::$cache)) {
            return self::$cache[$cameraIp];
        }

        $warehouseCode = null;

        if (Schema::hasTable('devices') && Schema::hasTable('warehouses')) {
            $warehouseCode = DB::table('devices')
                ->join('warehouses', 'warehouses.id', '=', 'devices.warehouse_id')
                ->where('devices.ip_address', $cameraIp)
                ->whereNull('devices.deleted_at')
                ->value('warehouses.warehouse_code');
        }

        // Mohali camera addresses are allocated from this subnet. Resolve its code
        // from the warehouse master so deployments can use their configured code.
        if (! $warehouseCode && preg_match('/^192\.168\.127\.\d{1,3}$/', $cameraIp)) {
            $warehouseCode = DB::table('warehouses')
                ->where(function ($query) {
                    $query->where('warehouse_name', 'like', '%Mohali%')
                        ->orWhere('city', 'like', '%Mohali%');
                })
                ->value('warehouse_code');
        }

        self::$cache[$cameraIp] = $warehouseCode ?: null;

        return self::$cache[$cameraIp];
    }
}
