<?php

namespace App\Http\Controllers;

use App\Models\FnsDetection;
use App\Models\FnsDetection02;
use App\Support\FnsDetectionHistory;
use App\Support\FnsDetectionRows;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FnsDetectionController extends Controller
{
    public function index(): View
    {
        return view('fns-detections.index');
    }

    public function index02(): View
    {
        return view('fns-detections.index02');
    }

    public function data(Request $request): JsonResponse
    {
        return $this->dataTable($request, FnsDetection::class);
    }

    public function data02(Request $request): JsonResponse
    {
        return $this->dataTable($request, FnsDetection02::class);
    }

    public function locations(): View
    {
        return view('fns-detections.locations', [
            'title' => 'FNS Detection Locations',
            'dataUrl' => route('fns-detections.locations.data'),
            'backUrl' => route('fns-detections.index'),
        ]);
    }

    public function locations02(): View
    {
        return view('fns-detections.locations', [
            'title' => 'FNS Detection Locations (02)',
            'dataUrl' => route('fns-detections02.locations.data'),
            'backUrl' => route('fns-detections02.index'),
        ]);
    }

    public function locationsData(): JsonResponse
    {
        return $this->locationSummary(FnsDetection::class, 'fns-detections.locations.destroy');
    }

    public function locationsData02(): JsonResponse
    {
        return $this->locationSummary(FnsDetection02::class, 'fns-detections02.locations.destroy');
    }

    public function destroyLocation(string $location): RedirectResponse
    {
        return $this->deleteLocation(FnsDetection::class, $location);
    }

    public function destroyLocation02(string $location): RedirectResponse
    {
        return $this->deleteLocation(FnsDetection02::class, $location);
    }

    /**
     * Every location (region / warehouse / godown / compartment) that has sent detections.
     *
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     */
    private function locationSummary(string $model, string $destroyRoute): JsonResponse
    {
        $groups = $this->localLocationGroups($model);

        if ($model === FnsDetection::class) {
            $groups = $groups->concat(FnsDetectionHistory::externalLocationGroups());
        }

        $locations = FnsDetectionRows::locations($groups)->map(fn (array $location) => $location + [
            // Only detections stored in our DB can be deleted; history API alerts are read-only.
            'delete_url' => $location['local_total'] > 0 ? route($destroyRoute, ['location' => $location['key']]) : null,
        ]);

        return response()->json([
            'data' => $locations,
        ]);
    }

    /**
     * Delete every stored detection (and its snapshot file) for one location.
     *
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     */
    private function deleteLocation(string $model, string $location): RedirectResponse
    {
        $groups = FnsDetectionRows::resolveLocations($this->localLocationGroups($model))
            ->where('key', $location);

        if ($groups->isEmpty()) {
            return redirect()->back()->with('error', 'Location not found or it has no stored detections.');
        }

        [$deleted, $snapshots] = DB::transaction(function () use ($model, $groups) {
            $query = $model::query()->where(function (Builder $query) use ($groups) {
                foreach ($groups as $group) {
                    $query->orWhere(function (Builder $query) use ($group) {
                        foreach (['camera_ip', 'camera_name', 'warehouse_code', 'godown', 'compartment'] as $field) {
                            $group[$field] === null
                                ? $query->whereNull($field)
                                : $query->where($field, $group[$field]);
                        }
                    });
                }
            });

            $snapshots = (clone $query)->whereNotNull('snapshot_path')->pluck('snapshot_path');

            return [$query->delete(), $snapshots];
        });

        $this->deleteSnapshots($snapshots);

        $first = $groups->first();

        return redirect()->back()->with('success', $deleted . ' detection(s) deleted for '
            . $first['warehouse_name'] . ' (' . $first['location'] . ').');
    }

    /**
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     * @return Collection<int, array<string, mixed>>
     */
    private function localLocationGroups(string $model): Collection
    {
        return $model::query()
            ->selectRaw('camera_ip, camera_name, warehouse_code, godown, compartment, COUNT(*) as total, MAX(detected_at) as last_detected_at')
            ->groupBy('camera_ip', 'camera_name', 'warehouse_code', 'godown', 'compartment')
            ->get()
            ->map(fn ($group) => $group->getAttributes() + ['source' => 'local']);
    }

    /**
     * Remove snapshot files stored on the public disk; legacy external URLs are left alone.
     *
     * @param  Collection<int, string>  $snapshots
     */
    private function deleteSnapshots(Collection $snapshots): void
    {
        $paths = $snapshots
            ->map(fn (string $path) => trim($path))
            ->reject(fn (string $path) => $path === '' || Str::startsWith(strtolower($path), ['http://', 'https://']))
            ->map(fn (string $path) => ltrim($path, '/'))
            ->map(fn (string $path) => Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path)
            ->unique()
            ->values()
            ->all();

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    /**
     * @param  class-string<FnsDetection|FnsDetection02>  $model
     */
    private function dataTable(Request $request, string $model): JsonResponse
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length < 1 || $length > 100) {
            $length = 10;
        }

        $filters = [
            'search' => data_get($request->query('search', []), 'value', ''),
        ];
        $query = $model::query()->filter($filters);

        if ($model === FnsDetection::class) {
            // Older detections come from the external history API.
            $slice = FnsDetectionHistory::slice($query, $filters, $start, $length);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $model::query()->count() + FnsDetectionHistory::externalTotal(),
                'recordsFiltered' => $slice['total'],
                'data' => FnsDetectionRows::format($slice['items']),
            ]);
        }

        $recordsTotal = $model::query()->count();
        $recordsFiltered = (clone $query)->count();

        $detections = $query
            ->latest('detected_at')
            ->latest('id')
            ->offset($start)
            ->limit($length)
            ->get();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => FnsDetectionRows::format($detections),
        ]);
    }
}
