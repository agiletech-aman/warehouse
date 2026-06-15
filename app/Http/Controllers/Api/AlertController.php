<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $active = $request->query('active'); // true/false/1/0
        if ($active !== null) {
            $activeStr = strtolower((string) $active);
            if (!in_array($activeStr, ['1', '0', 'true', 'false'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid active. Use 1/0 or true/false.',
                ], 422);
            }
        }

        $deviceId = $request->query('device_id');
        $startDate = $request->query('start_date'); // YYYY-MM-DD
        $endDate = $request->query('end_date');     // YYYY-MM-DD

        $base = Alert::query()->whereNull('deleted_at');

        if ($active !== null) {
            $activeBool = in_array(strtolower((string) $active), ['1', 'true'], true);
            $base->where('active', $activeBool);
        }

        if ($deviceId) {
            $base->where('device_id', $deviceId);
        }

        if ($startDate) {
            $base->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $base->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $total = (clone $base)->count();

        // counts using alerts.type: severe/critical
        $counts = [
            'severe' => (clone $base)->where('type', 'severe')->count(),
            'critical' => (clone $base)->where('type', 'critical')->count(),
            'total' => $total,
        ];

        // active alert count (for the same filters, but active=true)
        $activeCount = (clone $base)
            ->where('active', true)
            ->count();

        $page = (int) $request->query('page', 1);
        if ($page <= 0) {
            $page = 1;
        }

        $items = (clone $base)
            ->latest('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'id',
                'device_id',
                'reading_id',
                'alert_type',
                'alert_value',
                'type',
                'message',
                'last_email_at',
                'active',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Alerts fetched successfully',
            'filters' => [
                'active' => $active,
                'device_id' => $deviceId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'count' => $total,
            'counts' => $counts,
            'active_count' => $activeCount,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'count_returned' => $items->count(),
            ],
            'data' => $items,
        ]);
    }
}

