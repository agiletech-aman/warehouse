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
            ->whereNotNull('uuid')
            ->whereHas('region', function ($query) use ($state) {
                $query->whereRaw('LOWER(region_name) = ?', [strtolower(trim($state))]);
            })
            ->orderBy('warehouse_name')
            ->get(['uuid', 'warehouse_name', 'region_uuid'])
            ->filter(fn (Warehouse $warehouse) => ctype_digit((string) $warehouse->uuid))
            ->map(fn (Warehouse $warehouse) => $this->formatLocationName($warehouse->warehouse_name))
            ->unique()
            ->values();

        return response()->json($locations);
    }

    public function citiesByWarehouse(string $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::query()
            ->where('uuid', $warehouseId)
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
        $request->merge(['region_uuid' => $regionId]);

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

        $regionUuid = $request->query('region_uuid');

        $warehouses = Warehouse::query()
            ->with('region:uuid,region_name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($regionUuid, fn ($query) => $query->where('region_uuid', $regionUuid))
            ->whereNotNull('uuid')
            ->whereNotNull('region_uuid')
            ->orderBy('warehouse_name')
            ->get(['uuid', 'region_uuid', 'warehouse_name'])
            ->filter(fn (Warehouse $warehouse) => ctype_digit((string) $warehouse->uuid)
                && ctype_digit((string) $warehouse->region_uuid))
            ->map(fn (Warehouse $warehouse) => [
                'id' => (int) $warehouse->uuid,
                'warehouse_name' => $warehouse->uuid === '50'
                    ? 'RAIGARH-I'
                    : $warehouse->warehouse_name,
                'region_id' => (int) $warehouse->region_uuid,
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
        $romanNumbers = [
            'IV' => '4',
            'III' => '3',
            'II' => '2',
            'I' => '1',
        ];

        $name = preg_replace_callback(
            '/-(IV|III|II|I)(?=$|\()/i',
            fn (array $matches) => '-'.$romanNumbers[strtoupper($matches[1])],
            $warehouseName
        );

        return Str::title(strtolower((string) $name));
    }
}
