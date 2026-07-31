<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FnsDetection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FnsDetectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'camera_ip' => ['nullable', 'string', 'max:45'],
            'camera_name' => ['nullable', 'string', 'max:255'],
            'warehouse_code' => ['nullable', 'string', 'max:100'],
            'godown' => ['nullable', 'string', 'max:255'],
            'compartment' => ['nullable', 'string', 'max:255'],
            'detection_type' => [
                'nullable',
                Rule::in(['person', 'fire', 'smoke', 'weapon', 'intrusion']),
            ],
            'min_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'max_confidence' => [
                'nullable',
                'numeric',
                'between:0,1',
                Rule::when($request->filled('min_confidence'), ['gte:min_confidence']),
            ],
            'from_date' => ['nullable', 'date'],
            'to_date' => [
                'nullable',
                'date',
                Rule::when($request->filled('from_date'), ['after_or_equal:from_date']),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $detections = FnsDetection::query()
            ->filter($validated)
            ->latest('detected_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Detections fetched successfully.',
            'data' => $detections->items(),
            'pagination' => [
                'current_page' => $detections->currentPage(),
                'per_page' => $detections->perPage(),
                'total' => $detections->total(),
                'last_page' => $detections->lastPage(),
                'from' => $detections->firstItem(),
                'to' => $detections->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'camera_ip' => [
                'required',
                'string',
                'max:45',
            ],
            'camera_name' => [
                'required',
                'string',
                'max:255',
            ],
            'warehouse_code' => [
                'nullable',
                'string',
                'max:100',
            ],
            'godown' => [
                'nullable',
                'string',
                'max:255',
            ],
            'compartment' => [
                'nullable',
                'string',
                'max:255',
            ],
            'detection_type' => [
                'required',
                Rule::in([
                    'person',
                    'fire',
                    'smoke',
                    'weapon',
                    'intrusion',
                ]),
            ],
            'confidence' => [
                'required',
                'numeric',
                'between:0,1',
            ],
            'snapshot_path' => [
                'nullable',
                'string',
                'max:500',
            ],
            'bounding_box' => [
                'nullable',
                'string',
                'max:100',
            ],
            'detected_at' => [
                'nullable',
                'date',
            ],
        ]);

        $detection = FnsDetection::create([
            'id' => (string) Str::uuid(),
            'camera_ip' => $validated['camera_ip'],
            'camera_name' => $validated['camera_name'],
            'warehouse_code' => $validated['warehouse_code'] ?? null,
            'godown' => $validated['godown'] ?? null,
            'compartment' => $validated['compartment'] ?? null,
            'detection_type' => $validated['detection_type'],
            'confidence' => $validated['confidence'],
            'snapshot_path' => $validated['snapshot_path'] ?? null,
            'bounding_box' => $validated['bounding_box'] ?? null,
            'detected_at' => $validated['detected_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detection saved successfully.',
            'data' => $detection,
        ], 201);
    }
}
