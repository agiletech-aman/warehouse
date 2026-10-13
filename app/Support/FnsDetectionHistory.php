<?php

namespace App\Support;

use App\Models\FnsDetection;
use App\Models\FnsDetection02;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Combines local FNS detections (fns_detections or fns_detections_02) with every alert from the
 * external camera-alerts API, merged newest first by detection time.
 */
class FnsDetectionHistory
{
    // Pages carrying image_base64 are ~45MB each, so only a few are downloaded at once.
    private const PAGE_BATCH = 4;

    // The history API rejects pageSize above 500 (it answers with a health check instead of data).
    private const PAGE_SIZE = 500;

    /**
     * Newest-first slice of local + external detections.
     *
     * @param  Builder<FnsDetection|FnsDetection02>  $query  Local query with filters already applied.
     * @param  array<string, mixed>  $filters
     * @return array{items: Collection<int, FnsDetection|FnsDetection02>, total: int, local_total: int}
     */
    public static function slice(Builder $query, array $filters, int $offset, int $limit): array
    {
        $model = get_class($query->getModel());
        $localTotal = (clone $query)->count();
        $external = self::externalAlerts($filters, $model);
        $externalTotal = $external->count();

        // Both sources cover the same dates, so merge the two newest-first lists up to the end of
        // the requested page. Local rows are read as id + detected_at until the page is known.
        $end = $offset + $limit;
        $local = (clone $query)
            ->latest('detected_at')
            ->latest('id')
            ->limit($end)
            ->toBase()
            ->get(['id', 'detected_at'])
            ->values();
        // Only alerts that can land on this page become models; the full history is too large for that.
        $external = $external->take($end)->map(fn (array $alert) => self::toModel($alert, $model))->values();

        $page = [];
        $l = 0;
        $e = 0;

        for ($i = 0; $i < $end && ($l < $local->count() || $e < $external->count()); $i++) {
            $takeLocal = $e >= $external->count()
                || ($l < $local->count()
                    && Carbon::parse($local[$l]->detected_at)->gte($external[$e]->detected_at));

            $entry = $takeLocal ? ['local', $local[$l++]->id] : ['external', $external[$e++]];

            if ($i >= $offset) {
                $page[] = $entry;
            }
        }

        $localIds = collect($page)->where(0, 'local')->pluck(1)->all();
        $localModels = $localIds === [] ? collect() : $model::query()->whereKey($localIds)->get()->keyBy('id');

        $items = collect($page)
            ->map(fn (array $entry) => $entry[0] === 'local' ? $localModels->get($entry[1]) : $entry[1])
            ->filter()
            ->values();

        return [
            'items' => $items,
            'total' => $localTotal + $externalTotal,
            'local_total' => $localTotal,
        ];
    }

    /**
     * Count of all external detections (unfiltered).
     *
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     */
    public static function externalTotal(string $model): int
    {
        if (! self::historyEnabled()) {
            return 0;
        }

        $hidden = self::hiddenLocationKeys($model);

        return self::cachedAlerts()->reject(fn (array $alert) => isset($hidden[self::locationKey($alert)]))->count();
    }

    /**
     * External detections grouped per camera and location, in the shape FnsDetectionRows::locations() expects.
     *
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     * @return Collection<int, array<string, mixed>>
     */
    public static function externalLocationGroups(string $model): Collection
    {
        return self::externalDetections([], $model)
            ->groupBy(fn ($detection) => implode('|', [$detection->camera_ip, $detection->camera_name, $detection->godown, $detection->compartment]))
            ->map(function (Collection $detections) {
                $first = $detections->first();

                return [
                    'camera_ip' => $first->camera_ip,
                    'camera_name' => $first->camera_name,
                    'warehouse_code' => null,
                    'godown' => $first->godown,
                    'compartment' => $first->compartment,
                    'total' => $detections->count(),
                    'last_detected_at' => $detections->max('detected_at'),
                    'source' => 'external',
                ];
            })
            ->values();
    }

    /**
     * Hide the given external camera/location groups for one detections table; the history API itself has no delete.
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     */
    public static function hideLocations(Collection $groups, string $model): void
    {
        $hidden = self::hiddenLocationKeys($model);
        $table = (new $model)->getTable();
        $now = now();

        $rows = $groups
            ->reject(fn (array $group) => isset($hidden[self::locationKey($group)]))
            ->map(fn (array $group) => [
                'detections_table' => $table,
                'camera_ip' => $group['camera_ip'] ?? null,
                'camera_name' => $group['camera_name'] ?? null,
                'godown' => $group['godown'] ?? null,
                'compartment' => $group['compartment'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('fns_hidden_history_locations')->insert($rows);
        }
    }

    /**
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     * @return array<string, true>
     */
    private static function hiddenLocationKeys(string $model): array
    {
        if (! Schema::hasTable('fns_hidden_history_locations')) {
            return [];
        }

        $table = (new $model)->getTable();

        return DB::table('fns_hidden_history_locations')
            ->where(function ($query) use ($table) {
                $query->where('detections_table', $table);

                // Rows hidden before detections_table existed belong to fns_detections.
                if ($table === (new FnsDetection)->getTable()) {
                    $query->orWhereNull('detections_table');
                }
            })
            ->get(['camera_ip', 'camera_name', 'godown', 'compartment'])
            ->mapWithKeys(fn ($row) => [self::locationKey((array) $row) => true])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private static function locationKey(array $location): string
    {
        return implode('|', array_map(
            fn (string $field) => trim((string) ($location[$field] ?? '')),
            ['camera_ip', 'camera_name', 'godown', 'compartment'],
        ));
    }

    /**
     * All external detections, filtered and newest first.
     *
     * @param  array<string, mixed>  $filters
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     * @return Collection<int, FnsDetection|FnsDetection02>
     */
    public static function externalDetections(array $filters, string $model): Collection
    {
        return self::externalAlerts($filters, $model)
            ->map(fn (array $alert) => self::toModel($alert, $model))
            ->values();
    }

    /**
     * All external alerts as plain arrays, filtered and newest first.
     *
     * @param  array<string, mixed>  $filters
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     * @return Collection<int, array<string, mixed>>
     */
    private static function externalAlerts(array $filters, string $model): Collection
    {
        if (! self::historyEnabled()) {
            return collect();
        }

        $hidden = self::hiddenLocationKeys($model);

        return self::cachedAlerts()
            ->reject(fn (array $alert) => isset($hidden[self::locationKey($alert)]))
            ->filter(fn (array $alert) => self::matches($alert, $filters))
            ->sortByDesc(fn (array $alert) => $alert['detected_at']->getTimestamp() . '.' . str_pad((string) $alert['source_id'], 20, '0', STR_PAD_LEFT))
            ->values();
    }

    private static function historyEnabled(): bool
    {
        return config('fns.history.enabled') && filled(config('fns.history.url'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function cachedAlerts(): Collection
    {
        $alerts = Cache::store(config('fns.history.cache_store'))->get(self::cacheKey());

        if (! is_array($alerts)) {
            $alerts = self::refreshCache();
        }

        return collect($alerts ?? [])->map(function (array $alert) {
            $alert['detected_at'] = Carbon::parse($alert['detected_at']);

            return $alert;
        });
    }

    /**
     * Re-fetch the full history into the cache. A full fetch takes over a minute, so the scheduler
     * keeps the cache warm instead of a page request paying for it.
     *
     * @return array<int, array<string, mixed>>|null  null when the fetch failed
     */
    public static function refreshCache(): ?array
    {
        // Pages that carry image_base64 make a full fetch take ~1 minute and ~300MB.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $alerts = self::fetchAlerts();

        // Do not cache a failed fetch, so the next attempt retries.
        if ($alerts !== null) {
            Cache::store(config('fns.history.cache_store'))
                ->put(self::cacheKey(), $alerts, now()->addMinutes((int) config('fns.history.cache_minutes', 30)));
        }

        return $alerts;
    }

    /**
     * The history API's image_base64 for one alert as a data: URI, or null when the API has none yet.
     */
    public static function imageBase64(?string $sourceId): ?string
    {
        if (blank($sourceId)) {
            return null;
        }

        $image = Cache::store(config('fns.history.cache_store'))->get(self::imageCacheKey($sourceId));

        return is_string($image) && $image !== '' ? $image : null;
    }

    private static function imageCacheKey(string $sourceId): string
    {
        return 'fns_detection_history_image:' . $sourceId;
    }

    /**
     * image_base64 as a data: URI; the API may send it with or without the data: prefix.
     */
    private static function imageDataUri(mixed $base64): ?string
    {
        $base64 = trim((string) $base64);

        if ($base64 === '') {
            return null;
        }

        if (Str::startsWith($base64, 'data:')) {
            return $base64;
        }

        $mimeType = @getimagesizefromstring((string) base64_decode(substr($base64, 0, 4096), false))['mime'] ?? 'image/jpeg';

        return 'data:' . $mimeType . ';base64,' . $base64;
    }

    private static function cacheKey(): string
    {
        return 'fns_detection_history:v2:' . md5(json_encode([config('fns.history.url'), config('fns.history.alert_types')]));
    }

    /**
     * Fetch every page of every configured alert type.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private static function fetchAlerts(): ?array
    {
        $url = config('fns.history.url');
        $alerts = [];
        $imageStore = Cache::store(config('fns.history.cache_store'));

        try {
            // No configured types means one unfiltered pass over every alert type.
            foreach ((array) config('fns.history.alert_types') ?: [null] as $alertType) {
                // No page or date limit: keep reading until the API runs out of data.
                for ($page = 1; ; $page += self::PAGE_BATCH) {
                    $pages = range($page, $page + self::PAGE_BATCH - 1);

                    $responses = Http::pool(fn (Pool $pool) => array_map(
                        fn (int $p) => $pool->as((string) $p)
                            ->timeout(15)
                            ->acceptJson()
                            ->get($url, array_filter(['alert_type' => $alertType, 'pageNumber' => $p, 'pageSize' => self::PAGE_SIZE])),
                        $pages,
                    ));

                    $reachedEnd = false;

                    foreach ($pages as $p) {
                        $response = $responses[(string) $p];

                        if ($response instanceof \Throwable || ! $response->successful()) {
                            throw new \RuntimeException('Page ' . $p . ' of ' . ($alertType ?? 'all') . ' alerts could not be fetched.');
                        }

                        $data = $response->json('data') ?? [];

                        foreach ($data as $row) {
                            $alert = self::normalize($row);

                            if ($alert === null) {
                                continue;
                            }

                            $alerts[$alert['source_id']] = $alert;

                            // Images are kept per alert, outside the list cache, so the list stays small.
                            $image = self::imageDataUri($row['image_base64'] ?? null);

                            if ($image !== null) {
                                $imageStore->put(self::imageCacheKey($alert['source_id']), $image, now()->addDays(7));
                            }
                        }

                        // The API pages with pageNumber/totalPages; it has no next_page_url.
                        if ($data === [] || $p >= (int) $response->json('totalPages', 0)) {
                            $reachedEnd = true;
                        }

                        unset($data, $responses[(string) $p]);
                    }

                    if ($reachedEnd) {
                        break;
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('FNS detection history fetch failed.', ['error' => $exception->getMessage()]);

            return null;
        }

        return array_values($alerts);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private static function normalize(array $row): ?array
    {
        if (empty($row['alert_date_time'])) {
            return null;
        }

        // alert_type looks like "{'Fire': 1}".
        $type = preg_match("/['\"]?([A-Za-z_ ]+)['\"]?\s*:/", (string) ($row['alert_type'] ?? ''), $matches)
            ? strtolower(trim($matches[1]))
            : strtolower(trim((string) ($row['alert_type'] ?? '')));

        return [
            'source_id' => (string) ($row['source_id'] ?? $row['id'] ?? Str::uuid()),
            'camera_ip' => $row['camera_ip'] ?? null,
            'camera_name' => $row['camera_name'] ?? null,
            'godown' => self::firstFilled($row['location_name'] ?? null, $row['city'] ?? null),
            'compartment' => self::firstFilled(
                trim(implode(' ', array_filter([$row['shad_name'] ?? null, $row['column_name'] ?? null]))),
            ),
            'detection_type' => $type,
            'snapshot_path' => $row['image_path'] ?? null,
            'detected_at' => Carbon::parse($row['alert_date_time'])->setTimezone(config('app.timezone'))->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $alert
     * @param  array<string, mixed>  $filters
     */
    private static function matches(array $alert, array $filters): bool
    {
        $contains = fn ($haystack, string $needle) => Str::contains(strtolower((string) $haystack), strtolower($needle));

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $range = FnsDetectionRows::searchDateRange($search);
            $found = collect(['camera_ip', 'camera_name', 'godown', 'compartment', 'detection_type', 'snapshot_path'])
                ->contains(fn (string $field) => $contains($alert[$field] ?? '', $search))
                || ($range !== null && $alert['detected_at']->between($range[0], $range[1]));

            if (! $found) {
                return false;
            }
        }

        foreach (['camera_ip', 'camera_name', 'godown', 'compartment'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));

            if ($value !== '' && ! $contains($alert[$field] ?? '', $value)) {
                return false;
            }
        }

        // External alerts carry no warehouse code or confidence, so these filters exclude them.
        if (filled($filters['warehouse_code'] ?? null)
            || (isset($filters['min_confidence']) && $filters['min_confidence'] !== '')
            || (isset($filters['max_confidence']) && $filters['max_confidence'] !== '')) {
            return false;
        }

        if (! empty($filters['detection_type']) && $alert['detection_type'] !== $filters['detection_type']) {
            return false;
        }

        $date = $alert['detected_at']->toDateString();

        if (! empty($filters['from_date']) && $date < Carbon::parse($filters['from_date'])->toDateString()) {
            return false;
        }

        if (! empty($filters['to_date']) && $date > Carbon::parse($filters['to_date'])->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $alert
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     */
    private static function toModel(array $alert, string $model): FnsDetection|FnsDetection02
    {
        $detection = new $model([
            'id' => 'ext-' . $alert['source_id'],
            'camera_ip' => $alert['camera_ip'],
            'camera_name' => $alert['camera_name'],
            'warehouse_code' => null,
            'godown' => $alert['godown'],
            'compartment' => $alert['compartment'],
            'detection_type' => $alert['detection_type'],
            'confidence' => null,
            'snapshot_path' => $alert['snapshot_path'],
            'bounding_box' => null,
            'detected_at' => $alert['detected_at'],
        ]);

        $detection->setAttribute('source', 'external');
        $detection->setAttribute('source_id', $alert['source_id']);

        return $detection;
    }

    private static function firstFilled(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim($value);
            }
        }

        return null;
    }
}
