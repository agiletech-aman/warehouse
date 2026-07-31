<?php

namespace App\Http\Controllers;

use App\Models\FnsDetection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FnsDetectionController extends Controller
{
    public function index(): View
    {
        return view('fns-detections.index');
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->query('draw', 1);
        $start = max((int) $request->query('start', 0), 0);
        $length = (int) $request->query('length', 10);

        if ($length < 1 || $length > 100) {
            $length = 10;
        }

        $query = FnsDetection::query()->filter([
            'search' => data_get($request->query('search', []), 'value', ''),
        ]);
        $recordsTotal = FnsDetection::query()->count();
        $recordsFiltered = (clone $query)->count();

        $detections = $query
            ->latest('detected_at')
            ->latest('id')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn (FnsDetection $detection) => [
                'id' => $detection->id,
                'camera' => $this->joinParts($detection->camera_name, $detection->camera_ip),
                'warehouse_code' => $detection->warehouse_code ?: '-',
                'location' => $this->joinParts($detection->godown, $detection->compartment),
                'detection_type' => $detection->detection_type,
                'confidence' => round($detection->confidence * 100, 2),
                'snapshot_path' => $detection->snapshot_path ?: '-',
                'bounding_box' => $detection->bounding_box ?: '-',
                'detected_at' => $detection->detected_at?->format('d M Y H:i:s') ?: '-',
            ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $detections,
        ]);
    }

    private function joinParts(?string $first, ?string $second): string
    {
        $parts = array_values(array_filter([
            trim((string) $first),
            trim((string) $second),
        ], fn (string $part) => $part !== ''));

        return $parts ? implode(' / ', $parts) : '-';
    }
}
