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
                'warehouse_name' => $warehouseNames->get(self::normalizeWarehouseCode($detection->warehouse_code))
                    ?: $warehouseNamesByCameraIp->get($detection->camera_ip)
                    ?: '-',
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
     * Locations the detections come from, grouped by region, warehouse, godown and compartment.
     *
     * @param  Collection<int, object|array>  $groups  Rows with camera_ip, camera_name, warehouse_code,
     *                                                 godown, compartment, total, last_detected_at, source.
     * @return Collection<int, array<string, mixed>>
     */
    public static function locations(Collection $groups): Collection
    {
        return self::resolveLocations($groups)
            ->groupBy('key')
            ->map(function (Collection $rows, string $key) {
                $first = $rows->first();
                $lastDetectedAt = $rows->pluck('last_detected_at')->filter()->map(fn ($date) => Carbon::parse($date))->max();

                return [
                    'key' => $key,
                    'region_name' => $first['region_name'],
                    'warehouse_name' => $first['warehouse_name'],
                    'location' => $first['location'],
                    'total' => (int) $rows->sum('total'),
                    'last_detected_at' => $lastDetectedAt?->format('d M Y H:i:s') ?? '-',
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Add region, warehouse, location and the location key to each raw camera/location group.
     *
     * @param  Collection<int, object|array>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    public static function resolveLocations(Collection $groups): Collection
    {
        $groups = $groups->map(fn ($group) => (array) $group);
        $warehouses = self::warehouseRegionsFor($groups->pluck('warehouse_code'));
        $warehousesByCameraIp = self::warehouseRegionsForCameraIps($groups->pluck('camera_ip'));

        return $groups->map(function (array $group) use ($warehouses, $warehousesByCameraIp) {
            $byCode = $warehouses->get(self::normalizeWarehouseCode($group['warehouse_code'] ?? null), []);
            $byIp = $warehousesByCameraIp->get($group['camera_ip'] ?? null, []);

            $group['warehouse_name'] = ($byCode['warehouse_name'] ?? null)
                ?: ($byIp['warehouse_name'] ?? null)
                ?: (filled($group['warehouse_code'] ?? null) ? $group['warehouse_code'] : 'Unknown');
            $group['region_name'] = ($byCode['region_name'] ?? null) ?: ($byIp['region_name'] ?? null) ?: '-';
            $group['location'] = self::joinParts($group['godown'] ?? null, $group['compartment'] ?? null);
            $group['key'] = sha1($group['region_name'] . '|' . $group['warehouse_name'] . '|' . $group['location']);

            return $group;
        });
    }

    /**
     * Warehouse and region names keyed by normalized warehouse code.
     *
     * @return Collection<string, array{warehouse_name: ?string, region_name: ?string}>
     */
    private static function warehouseRegionsFor(Collection $warehouseCodes): Collection
    {
        $codes = $warehouseCodes->filter(fn ($code) => filled($code))->unique()->values();

        return $codes->isEmpty()
            ? collect()
            : Warehouse::withTrashed()
                ->leftJoin('regions', 'regions.frs_id', '=', 'warehouses.region_frs_id')
                ->whereRaw("UPPER(REPLACE(TRIM(warehouses.warehouse_code), '-', '')) IN (" . implode(',', array_fill(0, $codes->count(), '?')) . ')', $codes->map(fn ($code) => self::normalizeWarehouseCode($code))->all())
                ->get(['warehouses.warehouse_code', 'warehouses.warehouse_name', 'regions.region_name'])
                ->mapWithKeys(fn (Warehouse $warehouse) => [
                    self::normalizeWarehouseCode($warehouse->warehouse_code) => [
                        'warehouse_name' => $warehouse->warehouse_name,
                        'region_name' => $warehouse->region_name,
                    ],
                ]);
    }

    /**
     * Warehouse and region names keyed by camera IP.
     *
     * @return Collection<string, array{warehouse_name: ?string, region_name: ?string}>
     */
    private static function warehouseRegionsForCameraIps(Collection $cameraIps): Collection
    {
        $ips = $cameraIps->filter(fn ($ip) => filled($ip))->unique()->values();

        if ($ips->isEmpty()) {
            return collect();
        }

        $rows = collect();

        if (Schema::hasTable('devices')) {
            $rows = Warehouse::withTrashed()
                ->join('devices', 'devices.warehouse_id', '=', 'warehouses.id')
                ->leftJoin('regions', 'regions.frs_id', '=', 'warehouses.region_frs_id')
                ->whereIn('devices.ip_address', $ips)
                ->get(['devices.ip_address', 'warehouses.warehouse_name', 'regions.region_name'])
                ->mapWithKeys(fn ($row) => [$row->ip_address => [
                    'warehouse_name' => $row->warehouse_name,
                    'region_name' => $row->region_name,
                ]]);
        }

        if (Schema::hasTable('device_latest_status')) {
            $rows = $rows->union(DB::table('device_latest_status')
                ->whereIn('device_ip', $ips)
                ->whereNotNull('warehouse')
                ->get(['device_ip', 'warehouse', 'region'])
                ->mapWithKeys(fn ($row) => [$row->device_ip => [
                    'warehouse_name' => $row->warehouse,
                    'region_name' => $row->region,
                ]]));
        }

        return $rows;
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
