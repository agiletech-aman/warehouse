<?php

namespace App\Http\Controllers\Api;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Reading;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class ReadingController extends Controller
{
    public function indexWithSummary(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $deviceStatus = $request->query('device_status'); // active|inactive (from devices.status)
        if ($deviceStatus !== null && !in_array($deviceStatus, ['active', 'inactive'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device_status. Use active or inactive.',
            ], 422);
        }

        $level = $request->query('level'); // normal|severe|critical (reading level)
        if ($level !== null && !in_array($level, ['normal', 'severe', 'critical'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid level. Use normal, severe or critical.',
            ], 422);
        }

        $regionId = $request->query('region_id');
        $regionCode = $request->query('region_code');
        $regionName = $request->query('region_name');

        $warehouseId = $request->query('warehouse_id');
        $warehouseCode = $request->query('warehouse_code');
        $warehouseName = $request->query('warehouse_name');

        $startDate = $request->query('start_date'); // YYYY-MM-DD
        $endDate = $request->query('end_date');     // YYYY-MM-DD

        // basic recorded_at range filter
        $base = Reading::query();

        if ($level) {
            $base->where('level', $level);
        }

        if ($startDate) {
            $base->where('recorded_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $base->where('recorded_at', '<=', $endDate . ' 23:59:59');
        }

        // Prefer filtering by stored codes when available, but also support ID/name filters via relations.
        if ($regionCode) {
            $base->where('region_code', $regionCode);
        }
        if ($warehouseCode) {
            $base->where('warehouse_code', $warehouseCode);
        }

        if ($deviceStatus || $regionId || $regionName || $warehouseId || $warehouseName) {
            $base->whereHas('device', function ($q) use ($deviceStatus, $regionId, $regionName, $warehouseId, $warehouseName) {
                if ($deviceStatus) {
                    $q->where('status', $deviceStatus);
                }

                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }

                if ($warehouseName) {
                    $q->whereHas('warehouse', function ($wq) use ($warehouseName) {
                        $wq->where('warehouse_name', $warehouseName);
                    });
                }

                if ($regionId || $regionName) {
                    $q->whereHas('warehouse.region', function ($rq) use ($regionId, $regionName) {
                        if ($regionId) {
                            $rq->where('id', $regionId);
                        }
                        if ($regionName) {
                            $rq->where('region_name', $regionName);
                        }
                    });
                }
            });
        }

        // If only regionId/regionName provided but device_id is null for some readings,
        // they won't match the whereHas above. This is accepted as ingestion stores device_id when resolvable.

        // counts for same filtered dataset
        $countsQuery = (clone $base);
        $total = $countsQuery->count();

        $counts = [
            'normal' => (clone $base)->where('level', 'normal')->count(),
            'severe' => (clone $base)->where('level', 'severe')->count(),
            'critical' => (clone $base)->where('level', 'critical')->count(),
            'total' => $total,
        ];

        // pagination data
        $page = (int) $request->query('page', 1);
        if ($page <= 0) {
            $page = 1;
        }

        $dataQuery = (clone $base);
        $dataQuery->latest('recorded_at');

        $dataQuery->select([
            'id',
            // Return sensor identifier as device_id if FK device_id is null
            // (device_id is nullable in your schema).
            'sensor_device_id',
            'device_name',
            'device_type',
            'device_ip',
            'unit',
            'port',
            'region',
            'region_code',
            'warehouse',
            'warehouse_code',
            'godown',
            'compartment',
            'reading_value',
            'level',
            'status',
            'recorded_at',
        ]);


        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->offset($offset)->limit($perPage)->get();

        return response()->json([
            'success' => true,
            'message' => 'Readings fetched successfully (with severity counts)',
            'filters' => [
                'region_id' => $regionId,
                'region_code' => $regionCode,
                'region_name' => $regionName,
                'warehouse_id' => $warehouseId,
                'warehouse_code' => $warehouseCode,
                'warehouse_name' => $warehouseName,
                'device_status' => $deviceStatus,
                'level' => $level,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'count' => $total,
            'counts' => $counts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'count_returned' => $items->count(),
            ],
            'data' => $items,
        ]);
    }

//     public function store(Request $request)
//     {

//         $rows = [];

//         foreach ($request->readings as $reading) {
//             $row = [
//                 'key' => $request->key,

//                 // Store IoT sensor identifier directly as string (NOT FK).
//                 // Your payload uses device_id like: CO2_<ip>_<port>_<godown>.
//                 'sensor_device_id' => $reading['device_id'] ?? null,

//                 // FK is nullable now (migration added). Keep it NULL by default.
//                 'device_id' => null,

//                 'device_name' => $reading['device_name'] ?? null,
//                 'device_type' => $reading['device_type'] ?? null,
//                 'device_ip' => $reading['device_ip'] ?? null,

//                 'unit' => $reading['unit'] ?? null,
//                 'port' => $reading['port'] ?? null,

//                 'region' => $reading['region'] ?? null,
//                 'region_code' => $reading['region_code'] ?? null,

//                 'warehouse' => $reading['warehouse'] ?? null,
//                 'warehouse_code' => $reading['warehouse_code'] ?? null,

//                 'godown' => $reading['godown'] ?? null,
//                 'compartment' => $reading['compartment'] ?? null,

//                 'reading_value' => $reading['value'] ?? null,

//                 // level: normal/severe/critical
//                 'level' => strtolower($reading['level'] ?? 'normal'),

//                 // status: online/offline
//                 'status' => strtolower($reading['status'] ?? 'online'),

//                 'recorded_at' => $reading['recorded_at'] ?? now(),
//             ];

//             $createdReading = Reading::create($row);
//             $row['reading_id'] = $createdReading->id;

//             $rows[] = $row;
//         }

//         // Throttled alerting (email every 1 hour max per device+type)
//         // Also closes alert when status becomes normal.
//         $totalAlertsCreated = 0;
//         $totalAlertsClosed = 0;
//         $totalAlertAttempts = 0;
//         $alertEmailsSent = 0;

//         // IoT device resolution helpers
//         $resolveDeviceId = function (?string $sensorDeviceId, ?string $sensorIp, $sensorPort) {
//             if (!$sensorDeviceId) {
//                 return null;
//             }

//             // 0) If payload sends actual device id (numeric)
//             if (is_numeric($sensorDeviceId)) {
//                 $byId = Device::where('id', (int) $sensorDeviceId)->first();
//                 if ($byId) {
//                     return $byId->id;
//                 }
//             }

//             // 1) Exact match by device_code
//             $byCode = Device::where('device_code', $sensorDeviceId)->first();
//             if ($byCode) {
//                 return $byCode->id;
//             }

//             // 2) Parse CO2_<ip>_<port>_<godown>
//             if (str_contains($sensorDeviceId, '_')) {
//                 $parts = explode('_', $sensorDeviceId);
//                 // [type, ip, port, godown...]
//                 $parsedIp = $parts[1] ?? null;
//                 $parsedPort = $parts[2] ?? null;

//                 $lookupIp = $sensorIp ?: $parsedIp;
//                 $lookupPort = $sensorPort ?: $parsedPort;

//                 if ($lookupIp) {
//                     // devices table stores IP in ip_address (no device_ip / port columns)
//                     $byIp = Device::where('ip_address', $lookupIp)->first();
//                     if ($byIp) {
//                         return $byIp->id;
//                     }
//                 }
//             }

//             return null;
//         };

//         $resolveWarehouseForRow = function (array $row) {
//             if (!empty($row['warehouse_code'])) {
//                 $warehouse = \App\Models\Warehouse::with('region')
//                     ->where('warehouse_code', $row['warehouse_code'])
//                     ->first();

//                 if ($warehouse) {
//                     return $warehouse;
//                 }
//             }

//             if (!empty($row['warehouse'])) {
//                 return \App\Models\Warehouse::with('region')
//                     ->where('warehouse_name', $row['warehouse'])
//                     ->first();
//             }

//             return null;
//         };

//         foreach ($rows as $row) {
//             $sensorDeviceId = $row['sensor_device_id'];
//             $sensorIp = $row['device_ip'];
//             $sensorPort = $row['port'];

//             $resolvedDeviceId = $row['device_id']; // currently null in IoT store flow
//             if (!$resolvedDeviceId) {
//                 $resolvedDeviceId = $resolveDeviceId($sensorDeviceId, $sensorIp, $sensorPort);
//             }

//             // If reading comes from this device and device is online => set devices.status online
//             $status = $row['status'] ?? 'online';
//             if ($resolvedDeviceId && strtolower($status) === 'online') {
//                 Device::where('id', $resolvedDeviceId)->update(['status' => 'online']);
//             }

//             $level = $row['level'] ?? 'normal';
//             $totalAlertAttempts++;

//             // Determine alert type + message
//             $type = null;
//             $message = null;

//             if ($level === 'critical') {
//                 $type = 'critical';
//                 $message = 'Critical Alert: Immediate action required.';
//             } elseif ($level === 'severe') {
//                 $type = 'severe';
//                 $message = 'severe: Parameter out of range.';
//             }

//             // device_offline handling (optional)
//             if ($type === null && $status === 'offline') {
//                 $type = 'severe';
//                 $message = 'Device is OFFLINE';
//             }

//             $legacyAlertType = $status === 'offline'
//                 ? 'device_offline'
//                 : ($type === 'critical' ? 'high_co2' : 'high_phosphorus');

//             // If normal condition: close any active alerts for this device+severe/critical.
//             if ($type === null) {
//                 $closed = Alert::where('device_id', $sensorDeviceId)
//                     ->whereIn('type', ['severe', 'critical'])
//                     ->where('active', true)
//                     ->update(['active' => false]);

//                 $totalAlertsClosed += (int) $closed;
//                 continue;
//             }

//             // Throttle by last_email_at for (device_id, type)
//             $alert = \App\Models\Alert::where('device_id', $sensorDeviceId)
//                 ->where('type', $type)
//                 ->where('active', true)
//                 ->first();

//             $alertTypeLabel = $type === 'critical' ? 'CRITICAL' : 'severe';

//             $buildMailBody = function () use ($row, $resolvedDeviceId, $sensorDeviceId, $message, $alertTypeLabel, $type, $resolveWarehouseForRow) {
//                 $deviceForMail = $resolvedDeviceId
//                     ? \App\Models\Device::with('warehouse.region')->find($resolvedDeviceId)
//                     : null;
//                 $warehouseForMail = $deviceForMail?->warehouse ?: $resolveWarehouseForRow($row);

//                 $regionName = $warehouseForMail?->region?->region_name ?: ($row['region'] ?? null);
//                 $regionCode = $warehouseForMail?->region?->region_code ?: ($row['region_code'] ?? null);
//                 $warehouseName = $warehouseForMail?->warehouse_name ?: ($row['warehouse'] ?? null);
//                 $warehouseCode = $warehouseForMail?->warehouse_code ?: ($row['warehouse_code'] ?? null);

//                 $sensorId = $sensorDeviceId;

//                 $recordedAt = isset($row['recorded_at'])
//                     ? \Carbon\Carbon::parse($row['recorded_at'])->format('d M Y H:i')
//                     : now()->format('d M Y H:i');

//                 $deviceName = $row['device_name'] ?? null;
//                 $deviceLine = $deviceName ? ($deviceName . ' (' . $sensorId . ')') : $sensorId;

//                 return "==============================\n"
//                     . "ATS WAREHOUSE ALERT\n"
//                     . "==============================\n"
//                     . "ALERT TYPE : {$alertTypeLabel}\n"
//                     . "MESSAGE    : {$message}\n"
//                     . "------------------------------\n"
//                     . "DEVICE     : {$deviceLine}\n"
//                     . "DEVICE IP  : " . ($row['device_ip'] ?? '-') . "\n"
//                     . "PORT       : " . ($row['port'] ?? '-') . "\n"
//                     . "------------------------------\n"
//                     . "REGION     : " . ($regionName ?? '-') . " (" . ($regionCode ?? '-') . ")\n"
//                     . "WAREHOUSE  : " . ($warehouseName ?? '-') . " (" . ($warehouseCode ?? '-') . ")\n"
//                     . "GODOWN      : " . ($row['godown'] ?? '-') . "\n"
//                     . "COMPARTMENT: " . ($row['compartment'] ?? '-') . "\n"
//                     . "------------------------------\n"
//                     . "READING    : value=" . ($row['reading_value'] ?? '-')
//                     . ", level=" . ($row['level'] ?? '-')
//                     . ", status=" . ($row['status'] ?? '-') . "\n"
//                     . "UNIT       : " . ($row['unit'] ?? '-') . "\n"
//                     . "RECORDED AT: {$recordedAt}\n"
//                     . "------------------------------\n"
//                     . "Generated by ATS IoT Monitoring\n";
//             };

//             $sendMail = function () use ($row, $resolvedDeviceId, $alertTypeLabel, $sensorDeviceId, $buildMailBody, $resolveWarehouseForRow, &$alertEmailsSent) {

//                 $deviceForMail = $resolvedDeviceId
//                     ? \App\Models\Device::with('warehouse')->find($resolvedDeviceId)
//                     : null;
//                 $warehouseForMail = $deviceForMail?->warehouse ?: $resolveWarehouseForRow($row);
//                 $managerEmail = $warehouseForMail?->manager_email;

//                 if (!$managerEmail) {
//                     $managerEmail = null;
//                     Log::warning('Mail not sent: manager_email missing', [
//                         'sensor_device_id' => $sensorDeviceId,
//                         'warehouse_code' => $row['warehouse_code'] ?? $row['warehouse'] ?? null,
//                         'resolved_device_id' => $resolvedDeviceId,
//                         'warehouse_id' => $warehouseForMail?->id,
//                         'alert_type' => $alertTypeLabel,
//                     ]);
//                     return;
//                 }


//                 try {
//                     $mailBody = $buildMailBody();

//                     $warehouseName = $warehouseForMail?->warehouse_name ?: ($row['warehouse'] ?? null);
//                     $warehouseCode = $warehouseForMail?->warehouse_code ?: ($row['warehouse_code'] ?? null);
//                     $target = ($warehouseCode && $warehouseName)
//                         ? "{$warehouseName} ({$warehouseCode})"
//                         : ($warehouseName ?: ($warehouseCode ?: 'Warehouse'));

//                     \Illuminate\Support\Facades\Mail::raw(
//                         $mailBody,
//                         function ($m) use ($managerEmail, $alertTypeLabel, $target) {
//                             $m->to($managerEmail)->subject('Warehouse Alert [' . $alertTypeLabel . ']: ' . $target);
//                         }
//                     );

//                     $alertEmailsSent++;
//                 } catch (\Throwable $e) {
//                     // swallow to avoid breaking ingestion
//                 }
//             };


//             $sendWhatsapp = function () use (
//                 $row,
//                 $resolvedDeviceId,
//                 $alertTypeLabel,
//                 $buildMailBody,
//                 $resolveWarehouseForRow,
//                 &$alertEmailsSent
//             ) {

//                 $deviceForMsg = $resolvedDeviceId
//                     ? \App\Models\Device::with('warehouse')->find($resolvedDeviceId)
//                     : null;

//                 $warehouseForMsg = $deviceForMsg?->warehouse ?: $resolveWarehouseForRow($row);

//                 $phone = $warehouseForMsg?->manager_phone;

//                 if (!$phone) {
//                     return;
//                 }

//                 $phone = preg_replace('/\D/', '', $phone);

//                 try {

//                     $msg = $buildMailBody();
//                     $response = Http::timeout(20)->asForm()->post(
//                         'http://wapi.asgsinfosystem.in/wapp/v2/api/send',
//                         [
//                             'apikey' => env('WAPI_KEY'),
//                             'mobile' => $phone,
//                             'msg' => $msg,
//                         ]
//                     );

//                     Log::info('WhatsApp sent', [
//                         'phone' => $phone,
//                         'response' => $response->body()
//                     ]);
//                 } catch (\Throwable $e) {

//                     Log::error('WhatsApp send failed', [
//                         'phone' => $phone,
//                         'error' => $e->getMessage()
//                     ]);
//                 }
//             };



//             if (!$alert) {
//                 $created = Alert::create([
//                     'device_id' => $sensorDeviceId,
//                     'reading_id' => $row['reading_id'] ?? null,
//                     'type' => $type ?? null,
//                     'message' => $message,


//                     'last_email_at' => now(),
//                     'active' => true,
//                     // legacy columns
//                     'alert_type' => $legacyAlertType,
//                     'alert_value' => $row['reading_value'] ?? 0,
//                 ]);

//                 $totalAlertsCreated += $created ? 1 : 0;

//                 $warehouseAlertThrottleKey = 'warehouse-alert-mail:' . sha1(implode('|', [
//                     $sensorDeviceId,
//                     $row['warehouse_code'] ?? $row['warehouse'] ?? '',
//                     $type,
//                     $row['reading_id'] ?? '',
//                 ]));

//                 if (\Illuminate\Support\Facades\Cache::add($warehouseAlertThrottleKey, true, now()->addHour())) {
//                     $sendMail();
//                     $sendWhatsapp();
//                 }

//                 continue;
//             }

//             Log::info('CACHE DEBUG', [
//     'reading_id' => $row['reading_id'] ?? 'NULL',
//     'cache_key' => $warehouseAlertThrottleKey,
//     'cache_add_result' => \Illuminate\Support\Facades\Cache::has($warehouseAlertThrottleKey),
// ]);

//             $last = $alert->last_email_at;
//             $canSend = !$last || \Carbon\Carbon::parse($last)->addHour()->lte(now());


//             if ($canSend) {

//                 $sendMail();

//                 $sendWhatsapp();

//                 $alert->update([
//                     'last_email_at' => now()
//                 ]);
//             }
//         }

        

//         return response()->json([
//             'success' => true,
//             'message' => 'Readings stored successfully',
//             'count' => count($rows),
//             'alerting' => [
//                 'attempts' => $totalAlertAttempts,
//                 'alerts_created' => $totalAlertsCreated,
//                 'alerts_closed' => $totalAlertsClosed,
//                 'alert_emails_sent' => $alertEmailsSent,
//             ],
//         ]);
//     }

public function store(Request $request)
{
    $rows = [];

    foreach ($request->readings as $reading) {
        $row = [
            'key' => $request->key,
            'sensor_device_id' => $reading['device_id'] ?? null,
            'device_id' => null,
            'device_name' => $reading['device_name'] ?? null,
            'device_type' => $reading['device_type'] ?? null,
            'device_ip' => $reading['device_ip'] ?? null,
            'unit' => $reading['unit'] ?? null,
            'port' => $reading['port'] ?? null,
            'region' => $reading['region'] ?? null,
            'region_code' => $reading['region_code'] ?? null,
            'warehouse' => $reading['warehouse'] ?? null,
            'warehouse_code' => $reading['warehouse_code'] ?? null,
            'godown' => $reading['godown'] ?? null,
            'compartment' => $reading['compartment'] ?? null,
            'reading_value' => $reading['value'] ?? null,
            'level' => strtolower($reading['level'] ?? 'normal'),
            'status' => strtolower($reading['status'] ?? 'online'),
            'recorded_at' => $reading['recorded_at'] ?? now(),
        ];

        $createdReading = Reading::create($row);
        $row['reading_id'] = $createdReading->id;
        $rows[] = $row;
    }

    $totalAlertsCreated = 0;
    $totalAlertsClosed = 0;
    $totalAlertAttempts = 0;
    $alertEmailsSent = 0;

    $resolveDeviceId = function (?string $sensorDeviceId, ?string $sensorIp, $sensorPort) {
        if (!$sensorDeviceId) return null;

        if (is_numeric($sensorDeviceId)) {
            $byId = Device::where('id', (int) $sensorDeviceId)->first();
            if ($byId) return $byId->id;
        }

        $byCode = Device::where('device_code', $sensorDeviceId)->first();
        if ($byCode) return $byCode->id;

        if (str_contains($sensorDeviceId, '_')) {
            $parts = explode('_', $sensorDeviceId);
            $parsedIp = $parts[1] ?? null;
            $lookupIp = $sensorIp ?: $parsedIp;

            if ($lookupIp) {
                $byIp = Device::where('ip_address', $lookupIp)->first();
                if ($byIp) return $byIp->id;
            }
        }

        return null;
    };

    $resolveWarehouseForRow = function (array $row) {
        if (!empty($row['warehouse_code'])) {
            $warehouse = \App\Models\Warehouse::with('region')
                ->where('warehouse_code', $row['warehouse_code'])
                ->first();
            if ($warehouse) return $warehouse;
        }

        if (!empty($row['warehouse'])) {
            return \App\Models\Warehouse::with('region')
                ->where('warehouse_name', $row['warehouse'])
                ->first();
        }

        return null;
    };

    foreach ($rows as $row) {
        $sensorDeviceId = $row['sensor_device_id'];
        $sensorIp       = $row['device_ip'];
        $sensorPort     = $row['port'];

        $resolvedDeviceId = $row['device_id'];
        if (!$resolvedDeviceId) {
            $resolvedDeviceId = $resolveDeviceId($sensorDeviceId, $sensorIp, $sensorPort);
        }

        $status = $row['status'] ?? 'online';
        if ($resolvedDeviceId && strtolower($status) === 'online') {
            Device::where('id', $resolvedDeviceId)->update(['status' => 'online']);
        }

        $level = $row['level'] ?? 'normal';
        $totalAlertAttempts++;

        $type    = null;
        $message = null;

        if ($level === 'critical') {
            $type    = 'critical';
            $message = 'Critical Alert: Immediate action required.';
        } elseif ($level === 'severe') {
            $type    = 'severe';
            $message = 'Severe: Parameter out of range.';
        }

        if ($type === null && $status === 'offline') {
            $type    = 'severe';
            $message = 'Device is OFFLINE';
        }

        $legacyAlertType = $status === 'offline'
            ? 'device_offline'
            : ($type === 'critical' ? 'high_co2' : 'high_phosphorus');

        // Normal — close active alerts
        if ($type === null) {
            $closed = Alert::where('device_id', $sensorDeviceId)
                ->whereIn('type', ['severe', 'critical'])
                ->where('active', true)
                ->update(['active' => false]);

            $totalAlertsClosed += (int) $closed;
            continue;
        }

        $alertTypeLabel = $type === 'critical' ? 'CRITICAL' : 'SEVERE';

        // ── buildMailBody ──────────────────────────────────────────
        $buildMailBody = function () use ($row, $resolvedDeviceId, $sensorDeviceId, $message, $alertTypeLabel, $resolveWarehouseForRow) {

            $deviceForMail    = $resolvedDeviceId
                ? \App\Models\Device::with('warehouse.region')->find($resolvedDeviceId)
                : null;
            $warehouseForMail = $deviceForMail?->warehouse ?: $resolveWarehouseForRow($row);

            $regionName    = $warehouseForMail?->region?->region_name  ?: ($row['region']         ?? null);
            $regionCode    = $warehouseForMail?->region?->region_code  ?: ($row['region_code']    ?? null);
            $warehouseName = $warehouseForMail?->warehouse_name        ?: ($row['warehouse']      ?? null);
            $warehouseCode = $warehouseForMail?->warehouse_code        ?: ($row['warehouse_code'] ?? null);

            $recordedAt = isset($row['recorded_at'])
                ? \Carbon\Carbon::parse($row['recorded_at'])->format('d M Y H:i')
                : now()->format('d M Y H:i');

            $deviceName = $row['device_name'] ?? null;
            $deviceLine = $deviceName ? ($deviceName . ' (' . $sensorDeviceId . ')') : $sensorDeviceId;

            return "==============================\n"
                . "ATS WAREHOUSE ALERT\n"
                . "==============================\n"
                . "ALERT TYPE : {$alertTypeLabel}\n"
                . "MESSAGE    : {$message}\n"
                . "------------------------------\n"
                . "DEVICE     : {$deviceLine}\n"
                . "DEVICE IP  : " . ($row['device_ip'] ?? '-') . "\n"
                . "------------------------------\n"
                . "REGION     : " . ($regionName    ?? '-') . " (" . ($regionCode    ?? '-') . ")\n"
                . "WAREHOUSE  : " . ($warehouseName ?? '-') . " (" . ($warehouseCode ?? '-') . ")\n"
                . "GODOWN     : " . ($row['godown']      ?? '-') . "\n"
                . "COMPARTMENT: " . ($row['compartment'] ?? '-') . "\n"
                . "------------------------------\n"
                . "READING    : value=" . ($row['reading_value'] ?? '-')
                . ", level="           . ($row['level']          ?? '-')
                . ", status="          . ($row['status']         ?? '-') . "\n"
                . "UNIT       : "      . ($row['unit'] ?? '-') . "\n"
                . "RECORDED AT: {$recordedAt}\n"
                . "------------------------------\n"
                . "Generated by ATS IoT Monitoring\n";
        };

        // ── sendMail ───────────────────────────────────────────────
        $sendMail = function () use ($row, $resolvedDeviceId, $alertTypeLabel, $sensorDeviceId, $buildMailBody, $resolveWarehouseForRow, &$alertEmailsSent) {

            $deviceForMail    = $resolvedDeviceId
                ? \App\Models\Device::with('warehouse')->find($resolvedDeviceId)
                : null;
            $warehouseForMail = $deviceForMail?->warehouse ?: $resolveWarehouseForRow($row);
            $managerEmail     = $warehouseForMail?->manager_email;

            if (!$managerEmail) {
                Log::warning('Mail not sent: manager_email missing', [
                    'sensor_device_id'   => $sensorDeviceId,
                    'warehouse_code'     => $row['warehouse_code'] ?? $row['warehouse'] ?? null,
                    'resolved_device_id' => $resolvedDeviceId,
                    'warehouse_id'       => $warehouseForMail?->id,
                    'alert_type'         => $alertTypeLabel,
                ]);
                return;
            }

            try {
                $mailBody = $buildMailBody();

                $warehouseName = $warehouseForMail?->warehouse_name ?: ($row['warehouse']      ?? null);
                $warehouseCode = $warehouseForMail?->warehouse_code ?: ($row['warehouse_code'] ?? null);
                $target = ($warehouseCode && $warehouseName)
                    ? "{$warehouseName} ({$warehouseCode})"
                    : ($warehouseName ?: ($warehouseCode ?: 'Warehouse'));

                \Illuminate\Support\Facades\Mail::raw(
                    $mailBody,
                    function ($m) use ($managerEmail, $alertTypeLabel, $target) {
                        $m->to($managerEmail)
                          ->subject('Warehouse Alert [' . $alertTypeLabel . ']: ' . $target);
                    }
                );

                $alertEmailsSent++;
                Log::info('Mail sent', [
                    'to'         => $managerEmail,
                    'alert_type' => $alertTypeLabel,
                ]);

            } catch (\Throwable $e) {
                Log::error('MAIL SEND FAILED', [
                    'error' => $e->getMessage(),
                ]);
            }
        };

        // ── sendWhatsapp ───────────────────────────────────────────
        $sendWhatsapp = function () use ($row, $resolvedDeviceId, $buildMailBody, $resolveWarehouseForRow) {

            $deviceForMsg    = $resolvedDeviceId
                ? \App\Models\Device::with('warehouse')->find($resolvedDeviceId)
                : null;
            $warehouseForMsg = $deviceForMsg?->warehouse ?: $resolveWarehouseForRow($row);
            $phone           = $warehouseForMsg?->manager_phone;

            if (!$phone) {
                Log::warning('WhatsApp not sent: manager_phone missing', [
                    'warehouse_code' => $row['warehouse_code'] ?? $row['warehouse'] ?? null,
                ]);
                return;
            }

            $phone = preg_replace('/\D/', '', $phone);

            try {
                $msg = $buildMailBody();

                $response = Http::timeout(20)->asForm()->post(
                    'http://wapi.asgsinfosystem.in/wapp/v2/api/send',
                    [
                        'apikey' => env('WAPI_KEY'),
                        'mobile' => $phone,
                        'msg'    => $msg,
                    ]
                );

                Log::info('WhatsApp sent', [
                    'phone'    => $phone,
                    'response' => $response->body(),
                ]);

            } catch (\Throwable $e) {
                Log::error('WhatsApp send failed', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        // ── Alert logic ────────────────────────────────────────────
        $alert = \App\Models\Alert::where('device_id', $sensorDeviceId)
            ->where('type', $type)
            ->where('active', true)
            ->first();

        if (!$alert) {
            // Naya alert — seedha mail bhejo, koi cache nahi
            $created = Alert::create([
                'device_id'     => $sensorDeviceId,
                'reading_id'    => $row['reading_id'] ?? null,
                'type'          => $type,
                'message'       => $message,
                'last_email_at' => null,
                'active'        => true,
                'alert_type'    => $legacyAlertType,
                'alert_value'   => $row['reading_value'] ?? 0,
            ]);

            $totalAlertsCreated += $created ? 1 : 0;

            Log::info('NEW ALERT - SENDING MAIL+WA', [
                'sensor_device_id' => $sensorDeviceId,
                'type'             => $type,
                'reading_id'       => $row['reading_id'] ?? null,
            ]);

            $sendMail();
            $sendWhatsapp();

            $created->update(['last_email_at' => now()]);

            continue;
        }

        // Existing alert — 1 hour throttle
        $last     = $alert->last_email_at;
        $canSend  = !$last || \Carbon\Carbon::parse($last)->addHour()->lte(now());

        Log::info('EXISTING ALERT CHECK', [
            'sensor_device_id' => $sensorDeviceId,
            'type'             => $type,
            'last_email_at'    => $last,
            'can_send'         => $canSend,
        ]);

        if ($canSend) {
            $sendMail();
            $sendWhatsapp();
            $alert->update(['last_email_at' => now()]);
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Readings stored successfully',
        'count'   => count($rows),
        'alerting' => [
            'attempts'          => $totalAlertAttempts,
            'alerts_created'    => $totalAlertsCreated,
            'alerts_closed'     => $totalAlertsClosed,
            'alert_emails_sent' => $alertEmailsSent,
        ],
    ]);
}
}
