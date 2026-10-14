<?php

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * History API rows carry their snapshot as a data: URI in image_base64, since those snapshots are plain
     * http URLs the browser blocks on the https site; local rows keep using snapshot_url.
     * AI rows get snapshot_mime_type / snapshot_base64 for every row instead.
     *
     * @param  Collection<int, Model>  $detections
     */
    public static function format(Collection $detections, bool $withSnapshotBase64 = false): Collection
    {
        // History API rows use the API's own image_base64; the snapshot URL is only fetched when it has none yet.
        $apiImages = $detections
            ->filter(fn (Model $detection) => $detection->getAttribute('source') === 'external')
            ->mapWithKeys(fn (Model $detection) => [$detection->id => FnsDetectionHistory::imageBase64($detection->getAttribute('source_id'))]);
        $snapshots = self::fetchSnapshots($detections
            ->filter(fn (Model $detection) => $detection->getAttribute('source') === 'external'
                ? $apiImages->get($detection->id) === null
                : $withSnapshotBase64)
            ->pluck('snapshot_path'));
        $warehouseNames = self::warehouseNamesFor($detections->pluck('warehouse_code'));
        $warehouseNamesByCameraIp = self::warehouseNamesForCameraIps($detections->pluck('camera_ip'));
        $warehouseNamesByLocation = self::warehouseNamesForLocations(
            $detections->filter(fn (Model $detection) => $detection->getAttribute('source') === 'external')->pluck('godown')
        );

        $rows = $detections->map(function (Model $detection) use ($apiImages, $snapshots, $warehouseNames, $warehouseNamesByCameraIp, $warehouseNamesByLocation, $withSnapshotBase64) {
            $isExternal = $detection->getAttribute('source') === 'external';

            $row = [
                'id' => $detection->id,
                'name' => $detection->camera_name ?: '-',
                'camera_ip' => $detection->camera_ip ?: '-',
                'warehouse_code' => $detection->warehouse_code ?: '-',
                'warehouse_name' => $isExternal
                    ? self::externalWarehouseName($detection->godown, $warehouseNamesByLocation)
                    : self::warehouseName($detection->warehouse_code, $detection->camera_ip, $warehouseNames, $warehouseNamesByCameraIp),
                'location' => self::joinParts($detection->godown, $detection->compartment),
                'detection_type' => $detection->detection_type,
                'confidence' => $detection->confidence === null ? '-' : round($detection->confidence * 100, 2),
                'snapshot_path' => $detection->snapshot_path ?: '-',
                'snapshot_url' => $detection->snapshot_url,
                'bounding_box' => $detection->bounding_box ?: '-',
                'detected_at' => $detection->detected_at?->format('d M Y H:i:s') ?: '-',
                'source' => $detection->getAttribute('source') ?? 'local',
            ];

            $apiImage = $apiImages->get($detection->id);
            [$mimeType, $base64] = $withSnapshotBase64 && $apiImage !== null
                ? self::splitDataUri($apiImage)
                : self::encodeSnapshot($snapshots[trim((string) $detection->snapshot_path)] ?? null);

            if ($withSnapshotBase64) {
                $row['snapshot_mime_type'] = $mimeType;
                $row['snapshot_base64'] = $base64;
            } elseif ($isExternal) {
                $row['image_base64'] = $apiImage
                    ?? ($mimeType ? 'data:' . $mimeType . ';base64,' . $base64 : null);
            }

            return $row;
        })->values();

        // lynk_id / cwc_code come from the warehouse master: by warehouse_code when the row has one, else by name.
        $warehouses = self::warehouseIdsFor($rows->pluck('warehouse_code'), $rows->pluck('warehouse_name'));

        return $rows->map(function (array $row) use ($warehouses) {
            $code = self::normalizeWarehouseCode($row['warehouse_code'] === '-' ? '' : $row['warehouse_code']);
            $warehouse = ($code !== '' ? $warehouses['codes']->get($code) : null)
                ?? $warehouses['names']->get(strtolower(trim((string) $row['warehouse_name'])));

            return array_slice($row, 0, 4, true) + [
                'lynk_id' => $warehouse?->lynk_id,
                'cwc_code' => $warehouse?->cwc_code,
            ] + $row;
        });
    }

    /**
     * Warehouses keyed by normalized warehouse_code and by lowercased warehouse_name.
     *
     * @return array{codes: Collection<string, Warehouse>, names: Collection<string, Warehouse>}
     */
    private static function warehouseIdsFor(Collection $warehouseCodes, Collection $warehouseNames): array
    {
        $codes = $warehouseCodes->filter(fn ($code) => filled($code) && $code !== '-')
            ->map(fn ($code) => self::normalizeWarehouseCode($code))->unique()->values();
        $names = $warehouseNames->filter(fn ($name) => filled($name) && $name !== '-')->unique()->values();

        if ($codes->isEmpty() && $names->isEmpty()) {
            return ['codes' => collect(), 'names' => collect()];
        }

        $warehouses = Warehouse::withTrashed()
            ->where(function ($query) use ($codes, $names) {
                if ($codes->isNotEmpty()) {
                    $query->orWhereRaw("UPPER(REPLACE(TRIM(warehouse_code), '-', '')) IN (" . implode(',', array_fill(0, $codes->count(), '?')) . ')', $codes->all());
                }

                if ($names->isNotEmpty()) {
                    $query->orWhereIn('warehouse_name', $names);
                }
            })
            ->get(['warehouse_code', 'warehouse_name', 'lynk_id', 'cwc_code']);

        return [
            'codes' => $warehouses->keyBy(fn (Warehouse $warehouse) => self::normalizeWarehouseCode($warehouse->warehouse_code)),
            'names' => $warehouses->keyBy(fn (Warehouse $warehouse) => strtolower(trim((string) $warehouse->warehouse_name))),
        ];
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
                    'last_detected_ts' => $lastDetectedAt?->timestamp ?? 0,
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
        $warehouseNamesByLocation = self::warehouseNamesForLocations(
            $groups->where('source', 'external')->pluck('godown')
        );

        return $groups->map(function (array $group) use ($warehouseNames, $warehouseNamesByCameraIp, $warehouseNamesByLocation) {
            $group['warehouse_name'] = ($group['source'] ?? 'local') === 'external'
                ? self::externalWarehouseName($group['godown'] ?? null, $warehouseNamesByLocation)
                : self::warehouseName($group['warehouse_code'] ?? null, $group['camera_ip'] ?? null, $warehouseNames, $warehouseNamesByCameraIp);
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
     * Date or month typed into the detections search box (e.g. "07 Oct 2026", "07-10-2026", "2026-10-07",
     * "07 Oct 2026 14:30", "Oct 2026") as an inclusive [start, end] detected_at range.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function searchDateRange(string $search): ?array
    {
        $search = preg_replace('/\s+/', ' ', trim($search));

        $formats = [
            'second' => ['d M Y H:i:s', 'j M Y H:i:s', 'Y-m-d H:i:s', 'd-m-Y H:i:s', 'd/m/Y H:i:s'],
            'minute' => ['d M Y H:i', 'j M Y H:i', 'Y-m-d H:i', 'd-m-Y H:i', 'd/m/Y H:i'],
            'day' => ['d M Y', 'j M Y', 'd F Y', 'j F Y', 'Y-m-d', 'd-m-Y', 'j-n-Y', 'd/m/Y', 'j/n/Y', 'd.m.Y'],
            'month' => ['M Y', 'F Y', 'Y-m', 'm-Y', 'n-Y', 'm/Y', 'n/Y'],
        ];

        foreach ($formats as $unit => $unitFormats) {
            foreach ($unitFormats as $format) {
                if (! Carbon::hasFormat($search, $format)) {
                    continue;
                }

                try {
                    $date = Carbon::createFromFormat('!' . $format, $search);
                } catch (\Throwable) {
                    continue;
                }

                // Reject overflowed dates such as 31-02-2026 that PHP would roll into March.
                if ($date === null || $date->format($format) !== $search) {
                    continue;
                }

                return [$date->copy()->startOf($unit), $date->copy()->endOf($unit)];
            }
        }

        return null;
    }

    /**
     * History API alerts have no warehouse code and their camera_ip is a stream profile name shared
     * across sites, so the warehouse comes from the alert's location_name (stored in godown).
     */
    private static function externalWarehouseName(?string $locationName, Collection $warehouseNamesByLocation): string
    {
        $locationName = trim((string) $locationName);

        if ($locationName === '') {
            return '-';
        }

        return $warehouseNamesByLocation->get(strtolower($locationName)) ?: $locationName;
    }

    /**
     * Warehouse names keyed by lowercased name, for matching history API location names.
     */
    private static function warehouseNamesForLocations(Collection $locationNames): Collection
    {
        $names = $locationNames
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return collect();
        }

        return Warehouse::withTrashed()
            ->whereRaw('LOWER(TRIM(warehouse_name)) IN (' . implode(',', array_fill(0, $names->count(), '?')) . ')', $names->all())
            ->pluck('warehouse_name')
            ->mapWithKeys(fn (string $name) => [strtolower(trim($name)) => $name]);
    }

    /**
     * Snapshot file contents keyed by snapshot_path, from the public disk or (in parallel) from external URLs.
     *
     * @param  Collection<int, ?string>  $snapshotPaths
     * @return array<string, ?string>
     */
    private static function fetchSnapshots(Collection $snapshotPaths): array
    {
        $paths = $snapshotPaths->map(fn ($path) => trim((string) $path))->filter()->unique()->values();
        $isUrl = fn (string $path) => Str::startsWith(strtolower($path), ['http://', 'https://']);
        $contents = [];

        foreach ($paths->reject($isUrl) as $path) {
            $diskPath = ltrim($path, '/');

            if (Str::startsWith($diskPath, 'storage/')) {
                $diskPath = Str::after($diskPath, 'storage/');
            }

            try {
                $disk = Storage::disk('public');
                $contents[$path] = $disk->exists($diskPath) ? $disk->get($diskPath) : null;
            } catch (\Throwable) {
                $contents[$path] = null;
            }
        }

        // External snapshots never change, so cache them; failures are cached briefly so an
        // unreachable image host does not stall every page load.
        $cache = Cache::store(config('fns.history.cache_store'));
        $cacheKey = fn (string $url) => 'fns_snapshot:' . sha1($url);
        $urls = collect();

        foreach ($paths->filter($isUrl) as $url) {
            $cached = $cache->get($cacheKey($url));

            if ($cached === null) {
                $urls->push($url);
            } else {
                $contents[$url] = $cached === '' ? null : $cached;
            }
        }

        if ($urls->isNotEmpty()) {
            $responses = Http::pool(fn (Pool $pool) => $urls
                ->map(fn (string $url, int $i) => $pool->as((string) $i)->connectTimeout(2)->timeout(5)->get($url))
                ->all());

            foreach ($urls as $i => $url) {
                $response = $responses[(string) $i] ?? null;
                $body = $response instanceof Response && $response->successful() ? $response->body() : null;
                $usable = $body !== null && $body !== '' && strlen($body) <= self::MAX_SNAPSHOT_BYTES;

                // A cache that cannot be written (e.g. storage permissions) must not break the page.
                try {
                    $cache->put($cacheKey($url), $usable ? $body : '', $usable ? now()->addDay() : now()->addMinutes(10));
                } catch (\Throwable $exception) {
                    Log::warning('FNS snapshot could not be cached.', ['url' => $url, 'error' => $exception->getMessage()]);
                }

                $contents[$url] = $usable ? $body : null;
            }
        }

        return $contents;
    }

    /**
     * @return array{0: ?string, 1: ?string}  mime type and Base64 body, or nulls when it is not a usable image
     */
    private static function encodeSnapshot(?string $contents): array
    {
        if ($contents === null || $contents === '' || strlen($contents) > self::MAX_SNAPSHOT_BYTES) {
            return [null, null];
        }

        $mimeType = @getimagesizefromstring($contents)['mime'] ?? null;

        if ($mimeType === null) {
            return [null, null];
        }

        return [$mimeType, base64_encode($contents)];
    }

    /**
     * @return array{0: ?string, 1: ?string}  mime type and Base64 body of a data: URI
     */
    private static function splitDataUri(string $dataUri): array
    {
        if (! preg_match('/^data:([^;,]+)?(?:;[^,]*)?,(.*)$/s', $dataUri, $matches) || $matches[2] === '') {
            return [null, null];
        }

        return [$matches[1] ?: 'image/jpeg', $matches[2]];
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
