<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function regions(Request $request): JsonResponse
    {
        $status = $request->query('status');

        if ($status !== null && ! in_array($status, ['active', 'inactive'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Use active or inactive.',
            ], 422);
        }

        $regions = Region::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->whereNotNull('uuid')
            ->orderBy('region_name')
            ->get(['uuid', 'region_name'])
            ->filter(fn (Region $region) => ctype_digit((string) $region->uuid))
            ->map(fn (Region $region) => [
                'id' => (int) $region->uuid,
                'region_name' => $region->region_name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'count' => $regions->count(),
            'regions' => $regions,
        ]);
    }
}
