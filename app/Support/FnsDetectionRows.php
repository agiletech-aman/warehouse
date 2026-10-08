<?php

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the detection rows shown on the FNS detection index pages.
 */
class FnsDetectionRows
{
    private const MAX_SNAPSHOT_BYTES = 5 * 1024 * 1024;

    /**
     * @param  Collection<int, Model>  $detections
     */
    public static function format(Collection $detections, bool $withSnapshotBase64 = false): Collection
    {
        $warehouseNames = self::warehouseNamesFor($detections->pluck('warehouse_code'));
        $warehouseNamesByCameraIp = self::warehouseNamesForCameraIps($detections->pluck('camera_ip'));

        return $detections->map(function (Model $detection) use ($warehouseNames, $warehouseNamesByCameraIp, $withSnapshotBase64) {
            $row = [
                'id' => $detection->id,
                'name' => $detection->camera_name ?: '-',
                'camera_ip' => $detection->camera_ip ?: '-',
                'warehouse_code' => $detection->warehouse_code ?: '-',
                'warehouse_name' => self::warehouseName($detection->warehouse_code, $detection->camera_ip, $warehouseNames, $warehouseNamesByCameraIp),
                'location' => self::joinParts($detection->godown, $detection->compartment),
                'detection_type' => $detection->detection_type,
                'confidence' => $detection->confidence === null ? '-' : round($detection->confidence * 100, 2),
                'snapshot_path' => $detection->snapshot_path ?: '-',
                'snapshot_url' => $detection->snapshot_url,
                'bounding_box' => $detection->bounding_box ?: '-',
                'detected_at' => $detection->detected_at?->format('d M Y H:i:s') ?: '-',
                'source' => $detection->getAttribute('source') ?? 'local',
            ];

            if ($withSnapshotBase64) {
                [$row['snapshot_mime_type'], $row['snapshot_base64']] = self::snapshotBase64($detection->snapshot_path);
            }

            return $row;
        })->values();
    }

    /**
     * Warehouses the detections come from, with every godown / compartment counted together.
     *
     * @param  Collection<int, object|array>  $groups  Rows with camera_ip, camera_name, warehouse_code,
     *                                                 godown, compartment, total, last_detected_at, source.
     * @return Collection<int, array<string, mixed>>
     */
    public static function locations(Collection $groups): Collection
    {
        $resolved = self::resolveLocations($groups);
        $regionNames = self::regionNamesForWarehouses($resolved->pluck('warehouse_name'));

        return $resolved
            ->groupBy('key')
            ->map(function (Collection $rows, string $key) use ($regionNames) {
                $first = $rows->first();
                $lastDetectedAt = $rows->pluck('last_detected_at')->filter()->map(fn ($date) => Carbon::parse($date))->max();

                return [
                    'key' => $key,
                    'region_name' => $regionNames->get(strtolower(trim($first['warehouse_name']))) ?: '-',
                    'warehouse_name' => $first['warehouse_name'],
                    'location_count' => $rows->pluck('location')->unique()->count(),
                    'total' => (int) $rows->sum('total'),
                    'last_detected_at' => $lastDetectedAt?->format('d M Y H:i:s') ?? '-',
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Add the warehouse name, location and warehouse key to each raw camera/location group,
     * resolved exactly like the rows on the index page.
     *
     * @param  Collection<int, object|array>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    public static function resolveLocations(Collection $groups): Collection
    {
        $groups = $groups->map(fn ($group) => (array) $group);
        $warehouseNames = self::warehouseNamesFor($groups->pluck('warehouse_code'));
        $warehouseNamesByCameraIp = self::warehouseNamesForCameraIps($groups->pluck('camera_ip'));

        return $groups->map(function (array $group) use ($warehouseNames, $warehouseNamesByCameraIp) {
            $group['warehouse_name'] = self::warehouseName($group['warehouse_code'] ?? null, $group['camera_ip'] ?? null, $warehouseNames, $warehouseNamesByCameraIp);
            $group['location'] = self::joinParts($group['godown'] ?? null, $group['compartment'] ?? null);
            $group['key'] = sha1($group['warehouse_name']);

            return $group;
        });
    }

    private static function warehouseName(?string $warehouseCode, ?string $cameraIp, Collection $warehouseNames, Collection $warehouseNamesByCameraIp): string
    {
        return $warehouseNames->get(self::normalizeWarehouseCode($warehouseCode))
            ?: $warehouseNamesByCameraIp->get($cameraIp)
            ?: '-';
    }

    /**
     * Read the snapshot from the public disk (or a legacy external URL) as Base64.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function snapshotBase64(?string $snapshotPath): array
    {
        $snapshotPath = trim((string) $snapshotPath);

        if ($snapshotPath === '') {
            return [null, null];
        }

        $contents = null;

        try {
            if (Str::startsWith(strtolower($snapshotPath), ['http://', 'https://'])) {
                $response = Http::timeout(5)->get($snapshotPath);
                $contents = $response->successful() ? $response->body() : null;
            } else {
                $snapshotPath = ltrim($snapshotPath, '/');

                if (Str::startsWith($snapshotPath, 'storage/')) {
                    $snapshotPath = Str::after($snapshotPath, 'storage/');
                }

                $disk = Storage::disk('public');
                $contents = $disk->exists($snapshotPath) ? $disk->get($snapshotPath) : null;
            }
        } catch (\Throwable) {
            $contents = null;
        }

        if ($contents === null || $contents === '' || strlen($contents) > self::MAX_SNAPSHOT_BYTES) {
            return [null, null];
        }

        $mimeType = @getimagesizefromstring($contents)['mime'] ?? null;

        if ($mimeType === null) {
            return [null, null];
        }

        return [$mimeType, base64_encode($contents)];
    }

    private static function warehouseNamesFor(Collection $warehouseCodes): Collection
    {
        $codes = $warehouseCodes->filter(fn ($code) => filled($code))->unique()->values();

        return $codes->isEmpty()
            ? collect()
            : Warehouse::withTrashed()
                ->whereRaw("UPPER(REPLACE(TRIM(warehouse_code), '-', '')) IN (" . implode(',', array_fill(0, $codes->count(), '?')) . ')', $codes->map(fn ($code) => self::normalizeWarehouseCode($code))->all())
                ->get(['warehouse_code', 'warehouse_name'])
                ->mapWithKeys(fn (Warehouse $warehouse) => [
                    self::normalizeWarehouseCode($warehouse->warehouse_code) => $warehouse->warehouse_name,
                ]);
    }

    /**
     * Region names keyed by lowercased warehouse name; falls back to the region the sensors report.
     */
    private static function regionNamesForWarehouses(Collection $warehouseNames): Collection
    {
        $names = $warehouseNames->filter(fn ($name) => filled($name) && $name !== '-')->unique()->values();

        if ($names->isEmpty()) {
            return collect();
        }

        $regions = Warehouse::withTrashed()
            ->with('region:frs_id,region_name')
            ->whereIn('warehouse_name', $names)
            ->get(['warehouse_name', 'region_frs_id'])
            ->mapWithKeys(fn (Warehouse $warehouse) => [
                strtolower(trim($warehouse->warehouse_name)) => $warehouse->region?->region_name,
            ])
            ->filter();

        if (Schema::hasTable('device_latest_status')) {
            $regions = $regions->union(DB::table('device_latest_status')
                ->whereIn('warehouse', $names)
                ->whereNotNull('region')
                ->pluck('region', 'warehouse')
                ->mapWithKeys(fn ($region, $warehouse) => [strtolower(trim($warehouse)) => $region]));
        }

        return $regions;
    }

    private static function normalizeWarehouseCode(?string $warehouseCode): string
    {
        return strtoupper(str_replace('-', '', trim((string) $warehouseCode)));
    }

    private static function warehouseNamesForCameraIps(Collection $cameraIps): Collection
    {
        $ips = $cameraIps->filter(fn ($ip) => filled($ip))->unique()->values();

        if ($ips->isEmpty()) {
            return collect();
        }

        $names = collect();

        if (Schema::hasTable('devices')) {
            $names = Warehouse::withTrashed()
                ->join('devices', 'devices.warehouse_id', '=', 'warehouses.id')
                ->whereIn('devices.ip_address', $ips)
                ->pluck('warehouses.warehouse_name', 'devices.ip_address');
        }

        if (Schema::hasTable('device_latest_status')) {
            $names = $names->union(DB::table('device_latest_status')
                ->whereIn('device_ip', $ips)
                ->whereNotNull('warehouse')
                ->pluck('warehouse', 'device_ip'));
        }

        return $names;
    }

    private static function joinParts(?string $godown, ?string $compartment): string
    {
        $godown = trim((string) $godown);
        $compartment = trim((string) $compartment);

        $parts = array_values(array_filter([$godown, $compartment], fn (string $part) => $part !== ''));

        return $parts ? implode(' / ', $parts) : '-';
    }
}
