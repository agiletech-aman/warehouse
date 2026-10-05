<?php

namespace App\Support;

use App\Models\FnsDetection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Combines local FNS detections with the older alerts from the external camera-alerts API.
 *
 * Local rows are used from the first detection stored in our DB onwards; everything older
 * than that comes from the external API, so the two sources never overlap.
 */
class FnsDetectionHistory
{
    private const PAGE_BATCH = 10;

    /**
     * Newest-first slice of local + external detections.
     *
     * @param  Builder<FnsDetection>  $query  Local query with filters already applied.
     * @param  array<string, mixed>  $filters
     * @return array{items: Collection<int, FnsDetection>, total: int, local_total: int}
     */
    public static function slice(Builder $query, array $filters, int $offset, int $limit): array
    {
        $localTotal = (clone $query)->count();
        $external = self::externalDetections($filters);

        $items = collect();

        if ($offset < $localTotal) {
            $items = (clone $query)
                ->latest('detected_at')
                ->latest('id')
                ->offset($offset)
                ->limit($limit)
                ->get();
        }

        $remaining = $limit - $items->count();

        if ($remaining > 0) {
            $items = $items->concat($external->slice(max($offset - $localTotal, 0), $remaining))->values();
        }

        return [
            'items' => $items,
            'total' => $localTotal + $external->count(),
            'local_total' => $localTotal,
        ];
    }

    /**
     * Count of all external detections older than the local data (unfiltered).
     */
    public static function externalTotal(): int
    {
        return self::externalDetections([])->count();
    }

    /**
     * External detections older than the first local detection, filtered and newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, FnsDetection>
     */
    public static function externalDetections(array $filters): Collection
    {
        if (! config('fns.history.enabled') || blank(config('fns.history.url'))) {
            return collect();
        }

        $cutoff = FnsDetection::query()->min('detected_at');
        $cutoff = $cutoff ? Carbon::parse($cutoff) : null;

        return self::cachedAlerts()
            ->filter(fn (array $alert) => $cutoff === null || $alert['detected_at']->lt($cutoff))
            ->filter(fn (array $alert) => self::matches($alert, $filters))
            ->sortByDesc(fn (array $alert) => $alert['detected_at']->getTimestamp() . '.' . str_pad((string) $alert['source_id'], 20, '0', STR_PAD_LEFT))
            ->map(fn (array $alert) => self::toModel($alert))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function cachedAlerts(): Collection
    {
        $store = Cache::store(config('fns.history.cache_store'));
        $key = 'fns_detection_history:' . md5(json_encode([config('fns.history.url'), config('fns.history.alert_types')]));

        $alerts = $store->get($key);

        if (! is_array($alerts)) {
            $alerts = self::fetchAlerts();

            // Do not cache a failed fetch, so the next request retries.
            if ($alerts !== null) {
                $store->put($key, $alerts, now()->addMinutes((int) config('fns.history.cache_minutes', 30)));
            }
        }

        return collect($alerts ?? [])->map(function (array $alert) {
            $alert['detected_at'] = Carbon::parse($alert['detected_at']);

            return $alert;
        });
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

        try {
            foreach ((array) config('fns.history.alert_types', ['fire']) as $alertType) {
                // No page or date limit: keep reading until the API runs out of data.
                for ($page = 1; ; $page += self::PAGE_BATCH) {
                    $pages = range($page, $page + self::PAGE_BATCH - 1);

                    $responses = Http::pool(fn (Pool $pool) => array_map(
                        fn (int $p) => $pool->as((string) $p)
                            ->timeout(15)
                            ->acceptJson()
                            ->get($url, ['alert_type' => $alertType, 'page' => $p]),
                        $pages,
                    ));

                    $reachedEnd = false;

                    foreach ($pages as $p) {
                        $response = $responses[(string) $p];

                        if ($response instanceof \Throwable || ! $response->successful()) {
                            throw new \RuntimeException('Page ' . $p . ' of ' . $alertType . ' alerts could not be fetched.');
                        }

                        $data = $response->json('data') ?? [];

                        foreach ($data as $row) {
                            $alert = self::normalize($row);

                            if ($alert !== null) {
                                $alerts[$alert['source_id']] = $alert;
                            }
                        }

                        if ($data === [] || blank($response->json('next_page_url'))) {
                            $reachedEnd = true;
                        }
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
            $found = collect(['camera_ip', 'camera_name', 'godown', 'compartment', 'detection_type', 'snapshot_path'])
                ->contains(fn (string $field) => $contains($alert[$field] ?? '', $search));

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
     */
    private static function toModel(array $alert): FnsDetection
    {
        $detection = new FnsDetection([
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
