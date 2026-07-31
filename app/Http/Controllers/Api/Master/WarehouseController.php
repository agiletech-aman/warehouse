<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WarehouseController extends Controller
{
    public function locationsByState(string $state): JsonResponse
    {
        $locations = Warehouse::query()
            ->whereNotNull('frs_id')
            ->where('region_id', $state)
            ->orderBy('warehouse_name')
            ->get(['id', 'frs_id', 'nms_id', 'warehouse_name', 'region_frs_id'])
            ->filter(fn (Warehouse $warehouse) => ctype_digit((string) $warehouse->frs_id))
            ->map(fn (Warehouse $warehouse) => [
                'base_id' => $warehouse->id,
                'frs_id' => $warehouse->frs_id,
                'nms_id' => $warehouse->nms_id,
                'name' => $this->formatLocationName($warehouse->warehouse_name),
            ])
            ->unique('name')
            ->values();

        return response()->json($locations);
    }

    public function citiesByWarehouse(string $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::query()
            ->where('frs_id', $warehouseId)
            ->first(['warehouse_name', 'city']);

        if (! $warehouse) {
            return response()->json([]);
        }

        $city = trim((string) $warehouse->city);

        if ($city === '') {
            $city = $this->cityFromWarehouseName($warehouse->warehouse_name);
        }

        return response()->json($city !== '' ? [$city] : []);
    }

    public function warehousesByRegion(Request $request, string $regionId): JsonResponse
    {
        $request->merge(['region_frs_id' => $regionId]);

        return $this->warehouses($request);
    }

    public function warehouses(Request $request): JsonResponse
    {
        $status = $request->query('status');

        if ($status !== null && ! in_array($status, ['active', 'inactive'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Use active or inactive.',
            ], 422);
        }

        $regionFrsId = $request->query('region_frs_id');

        $warehouses = Warehouse::query()
            ->with('region:frs_id,region_name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($regionFrsId, fn ($query) => $query->where('region_frs_id', $regionFrsId))
            ->whereNotNull('frs_id')
            ->whereNotNull('region_frs_id')
            ->orderBy('warehouse_name')
            ->get(['frs_id', 'region_frs_id', 'warehouse_name'])
            ->filter(fn (Warehouse $warehouse) => ctype_digit((string) $warehouse->frs_id)
                && ctype_digit((string) $warehouse->region_frs_id))
            ->map(fn (Warehouse $warehouse) => [
                'id' => (int) $warehouse->frs_id,
                'warehouse_name' => $warehouse->frs_id === '50'
                    ? 'RAIGARH-I'
                    : $warehouse->warehouse_name,
                'region_id' => (int) $warehouse->region_frs_id,
                'region_name' => $warehouse->region?->region_name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'count' => $warehouses->count(),
            'warehouses' => $warehouses,
        ]);
    }

    private function cityFromWarehouseName(string $warehouseName): string
    {
        $name = preg_replace('/\s*\(PEG\)\s*/i', '', $warehouseName);
        $name = preg_replace('/\s*(?:-|\s)(?:I{1,4}|BD)\s*$/i', '', (string) $name);

        $knownCities = [
            'SRIGANGANAGAR' => 'Sri Ganga Nagar',
        ];

        $normalized = strtoupper(trim((string) $name));

        return $knownCities[$normalized]
            ?? Str::title(strtolower(str_replace(['-', '/'], ' ', $normalized)));
    }

    private function formatLocationName(string $warehouseName): string
    {
        return $warehouseName;
    }
}
