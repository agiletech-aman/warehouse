<?php

namespace App\Http\Controllers;

use App\Models\FnsDetection;
use App\Models\FnsDetection02;
use App\Support\FnsDetectionHistory;
use App\Support\FnsDetectionRows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
