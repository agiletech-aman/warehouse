<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FnsDetection;
use App\Models\FnsDetection02;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'snapshot' => [
                'nullable',
                // Supports a multipart image upload or a Base64 image string.
            ],
            'snapshot_base64' => [
                'nullable',
                'string',
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

        // Priority: multipart upload, Base64 snapshot, then the legacy path field.
        $snapshotPath = $this->storeSnapshot($request, $validated)
            ?? ($validated['snapshot_path'] ?? null);

        $detection = FnsDetection::create([
            'id' => (string) Str::uuid(),
            'camera_ip' => $validated['camera_ip'],
            'camera_name' => $validated['camera_name'],
            'warehouse_code' => $validated['warehouse_code'] ?? null,
            'godown' => $validated['godown'] ?? null,
            'compartment' => $validated['compartment'] ?? null,
            'detection_type' => $validated['detection_type'],
            'confidence' => $validated['confidence'],
            'snapshot_path' => $snapshotPath,
            'bounding_box' => $validated['bounding_box'] ?? null,
            'detected_at' => $validated['detected_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detection saved successfully.',
            'data' => $detection,
        ], 201);
    }

    /**
     * Store an uploaded image or a Base64 image on the public disk.
     *
     * @param  array<string, mixed>  $validated
     */
    private function storeSnapshot(Request $request, array $validated): ?string
    {
        if ($request->hasFile('snapshot')) {
            $request->validate([
                'snapshot' => ['required', 'image', 'max:5120'], // 5 MB
            ]);

            $snapshotPath = $request->file('snapshot')->storePublicly('snapshots', 'public');

            if ($snapshotPath === false) {
                throw new \RuntimeException('The snapshot image could not be stored.');
            }

            return $snapshotPath;
        }

        $base64Snapshot = $validated['snapshot_base64']
            ?? ($validated['snapshot'] ?? null);

        if ($base64Snapshot === null || $base64Snapshot === '') {
            return null;
        }

        if (! is_string($base64Snapshot)) {
            throw ValidationException::withMessages([
                'snapshot' => 'The snapshot must be an image file or a Base64-encoded image.',
            ]);
        }

        [$contents, $extension] = $this->decodeBase64Image($base64Snapshot);
        $snapshotPath = 'snapshots/' . Str::uuid() . '.' . $extension;

        if (! Storage::disk('public')->put($snapshotPath, $contents, ['visibility' => 'public'])) {
            throw new \RuntimeException('The snapshot image could not be stored.');
        }

        return $snapshotPath;
    }

     public function index02(Request $request): JsonResponse
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
        $detections = FnsDetection02::query()
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

    /**
     * @return array{0: string, 1: string}
     */
    private function decodeBase64Image(string $base64Snapshot): array
    {
        $encodedImage = trim($base64Snapshot);

        if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,(.+)$/s', $encodedImage, $matches)) {
            $encodedImage = $matches[1];
        }

        $encodedImage = preg_replace('/\s+/', '', $encodedImage);
        $contents = $encodedImage === null ? false : base64_decode($encodedImage, true);

        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages([
                'snapshot' => 'The snapshot must be a valid Base64-encoded image.',
            ]);
        }

        if (strlen($contents) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'snapshot' => 'The decoded snapshot image may not be greater than 5 MB.',
            ]);
        }

        $imageInfo = @getimagesizefromstring($contents);
        $mimeType = $imageInfo['mime'] ?? null;
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (! isset($extensions[$mimeType])) {
            throw ValidationException::withMessages([
                'snapshot' => 'The snapshot must be a JPEG, PNG, GIF, or WebP image.',
            ]);
        }

        return [$contents, $extensions[$mimeType]];
    }

    // public function store02(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'camera_ip' => [
    //             'required',
    //             'string',
    //             'max:45',
    //         ],
    //         'camera_name' => [
    //             'required',
    //             'string',
    //             'max:255',
    //         ],
    //         'warehouse_code' => [
    //             'nullable',
    //             'string',
    //             'max:100',
    //         ],
    //         'godown' => [
    //             'nullable',
    //             'string',
    //             'max:255',
    //         ],
    //         'compartment' => [
    //             'nullable',
    //             'string',
    //             'max:255',
    //         ],
    //         'detection_type' => [
    //             'required',
    //             Rule::in([
    //                 'person',
    //                 'fire',
    //                 'smoke',
    //                 'weapon',
    //                 'intrusion',
    //             ]),
    //         ],
    //         'confidence' => [
    //             'required',
    //             'numeric',
    //             'between:0,1',
    //         ],
    //         'snapshot' => [
    //             'nullable',
    //             // Supports a multipart image upload or a Base64 image string.
    //         ],
    //         'snapshot_base64' => [
    //             'nullable',
    //             'string',
    //         ],
    //         'snapshot_path' => [
    //             'nullable',
    //             'string',
    //             'max:500',
    //         ],
    //         'bounding_box' => [
    //             'nullable',
    //             'string',
    //             'max:100',
    //         ],
    //         'detected_at' => [
    //             'nullable',
    //             'date',
    //         ],
    //     ]);

    //     // Priority: multipart upload, Base64 snapshot, then the legacy path field.
    //     $snapshotPath = $this->storeSnapshot($request, $validated)
    //         ?? ($validated['snapshot_path'] ?? null);

    //     $detection = FnsDetection02::create([
    //         'id' => (string) Str::uuid(),
    //         'camera_ip' => $validated['camera_ip'],
    //         'camera_name' => $validated['camera_name'],
    //         'warehouse_code' => $validated['warehouse_code'] ?? null,
    //         'godown' => $validated['godown'] ?? null,
    //         'compartment' => $validated['compartment'] ?? null,
    //         'detection_type' => $validated['detection_type'],
    //         'confidence' => $validated['confidence'],
    //         'snapshot_path' => $snapshotPath,
    //         'bounding_box' => $validated['bounding_box'] ?? null,
    //         'detected_at' => $validated['detected_at'] ?? now(),
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Detection saved successfully.',
    //         'data' => $detection,
    //     ], 201);
    // }

    public function store02(Request $request): JsonResponse
{
    // Static Secret Key
    $secretKey = 'FIRESMOKe2026';

    if ($request->header('X-Push-Secret') !== $secretKey) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid push secret',
        ], 403);
    }

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
        'snapshot' => [
            'nullable',
        ],
        'snapshot_base64' => [
            'nullable',
            'string',
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

    // Priority: multipart upload, Base64 snapshot, then the legacy path field.
    $snapshotPath = $this->storeSnapshot($request, $validated)
        ?? ($validated['snapshot_path'] ?? null);

    $detection = FnsDetection02::create([
        'id' => (string) Str::uuid(),
        'camera_ip' => $validated['camera_ip'],
        'camera_name' => $validated['camera_name'],
        'warehouse_code' => $validated['warehouse_code'] ?? null,
        'godown' => $validated['godown'] ?? null,
        'compartment' => $validated['compartment'] ?? null,
        'detection_type' => $validated['detection_type'],
        'confidence' => $validated['confidence'],
        'snapshot_path' => $snapshotPath,
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
