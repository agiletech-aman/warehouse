<?php

namespace App\Http\Controllers\Api;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceLatestStatus;
use App\Models\Reading;
use App\Models\Region;
use App\Models\Warehouse;
use App\Services\DeviceLatestStatusService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\EmailRouting;
use App\Models\CcEmail;


class ReadingController extends Controller
{
    public function indexWithSummary(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $deviceStatus = $request->query('device_status');
        if ($deviceStatus !== null && !in_array($deviceStatus, ['active', 'inactive', 'online', 'offline'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device_status. Use online/offline (active/inactive are also accepted).',
            ], 422);
        }
        $normalizedDeviceStatus = match ($deviceStatus) {
            'active' => 'online',
            'inactive' => 'offline',
            default => $deviceStatus,
        };

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

        $startDate = $request->query('start_date')
            ?? $request->query('from_date')
            ?? $request->query('fromDate')
            ?? $request->query('from'); // YYYY-MM-DD
        $endDate = $request->query('end_date')
            ?? $request->query('to_date')
            ?? $request->query('toDate')
            ?? $request->query('to'); // YYYY-MM-DD

        // A range asks for history. Without one this endpoint is a live
        // current-device summary and must not derive latest rows from history.
        $useHistoricalReadings = $startDate !== null || $endDate !== null;

        $base = $useHistoricalReadings ? Reading::query() : DeviceLatestStatus::query();

        if ($level) {
            $base->where('level', $level);
        }

        if ($useHistoricalReadings && $startDate) {
            $base->where('recorded_at', '>=', $startDate . ' 00:00:00');
        }
        if ($useHistoricalReadings && $endDate) {
            $base->where('recorded_at', '<=', $endDate . ' 23:59:59');
        }

        // Prefer filtering by stored codes when available, but also support ID/name filters via relations.
        if ($regionCode) {
            $base->where('region_code', $regionCode);
        }
        if ($warehouseCode) {
            $base->where('warehouse_code', $warehouseCode);
        }

        if ($useHistoricalReadings && ($normalizedDeviceStatus || $regionId || $regionName || $warehouseId || $warehouseName)) {
            $base->whereHas('device', function ($q) use ($normalizedDeviceStatus, $regionId, $regionName, $warehouseId, $warehouseName) {
                if ($normalizedDeviceStatus) {
                    $q->where('status', $normalizedDeviceStatus);
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

        if (! $useHistoricalReadings) {
            if ($normalizedDeviceStatus) {
                $base->where('status', $normalizedDeviceStatus);
            }

            if ($warehouseId) {
                $warehouse = Warehouse::find($warehouseId, ['warehouse_code', 'warehouse_name']);
                $warehouse
                    ? $base->where(function ($query) use ($warehouse) {
                        $query->where('warehouse_code', $warehouse->warehouse_code)
                            ->orWhere('warehouse', $warehouse->warehouse_name);
                    })
                    : $base->whereRaw('1 = 0');
            }

            if ($warehouseName) {
                $base->where('warehouse', $warehouseName);
            }

            if ($regionId) {
                $region = Region::find($regionId, ['region_code', 'region_name']);
                $region
                    ? $base->where(function ($query) use ($region) {
                        $query->where('region_code', $region->region_code)
                            ->orWhere('region', $region->region_name);
                    })
                    : $base->whereRaw('1 = 0');
            }

            if ($regionName) {
                $base->where('region', $regionName);
            }
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

        $columns = [
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
        ];

        if ($useHistoricalReadings) {
            $dataQuery->select(['id', ...$columns]);
        } else {
            // The projection intentionally has no historical row id. Expose a
            // stable id key so this endpoint's JSON structure stays unchanged.
            $dataQuery->selectRaw('sensor_device_id as id')->addSelect($columns);
        }


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

    public function store(Request $request, DeviceLatestStatusService $latestStatus)
    {
        $rows = DB::transaction(function () use ($request, $latestStatus) {
            $rows = [];

        foreach ($request->readings as $index => $reading) {
            $readingValue = $reading['value'] ?? null;
            $incomingLevel = null;
            $incomingLevelKey = null;

            foreach (['level', 'Level', 'severity', 'reading_level'] as $candidateKey) {
                if (array_key_exists($candidateKey, $reading)) {
                    $incomingLevel = trim((string) $reading[$candidateKey]);
                    $incomingLevelKey = $candidateKey;
                    break;
                }
            }

            $storedLevel = match (strtolower((string) $incomingLevel)) {
                'warn', 'warning' => 'severe',
                'crit' => 'critical',
                '' => null,
                default => strtolower($incomingLevel),
            };

            if ($readingValue !== null && is_numeric($readingValue)) {
                if ($storedLevel === null) {
                    Log::warning('Numeric sensor reading received without a level; push accepted for debugging.', [
                        'reading_index' => $index,
                        'sensor_device_id' => $reading['device_id'] ?? null,
                        'reading_value' => $readingValue,
                        'status' => $reading['status'] ?? null,
                        'received_keys' => array_keys($reading),
                    ]);
                } elseif (!in_array($storedLevel, ['normal', 'severe', 'critical'], true)) {
                    Log::warning('Numeric sensor reading received with an unrecognized level; push accepted unchanged.', [
                        'reading_index' => $index,
                        'sensor_device_id' => $reading['device_id'] ?? null,
                        'reading_value' => $readingValue,
                        'status' => $reading['status'] ?? null,
                        'level_key' => $incomingLevelKey,
                        'level_received' => $incomingLevel,
                    ]);
                }
            }

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
                'reading_value' => $readingValue,
                'level' => $storedLevel,
                'status' => strtolower($reading['status'] ?? 'online'),
                'recorded_at' => $reading['recorded_at'] ?? now(),
            ];

            // The model observer covers regular Eloquent writes elsewhere.
            // Do this explicitly here so history and current state share one
            // transaction and the status write happens exactly once per row.
            $createdReading = Reading::withoutEvents(fn () => Reading::create($row));
            $latestStatus->upsertFromReading($createdReading);
            $row['reading_id'] = $createdReading->id;
            $rows[] = $row;
        }

            return $rows;
        });

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
            $sensorIp = $row['device_ip'];
            $sensorPort = $row['port'];

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

            $type = null;
            $message = null;

            if (strtolower($status) === 'offline') {
                // An offline device is not providing a usable reading, so its
                // reading severity cannot be determined.
                $type = 'unknown';
                $message = 'Device is OFFLINE';
            } elseif ($level === 'critical') {
                $type = 'critical';
                $message = 'Critical Alert: Immediate action required.';
            } elseif ($level === 'severe') {
                $type = 'severe';
                $message = 'Severe: Parameter out of range.';
            }

            $legacyAlertType = strtolower($status) === 'offline'
                ? 'device_offline'
                : ($type === 'critical' ? 'high_co2' : 'high_phosphorus');

            if ($type === null) {
                $closed = Alert::where('device_id', $sensorDeviceId)
                    ->whereIn('type', ['severe', 'critical', 'unknown'])
                    ->where('active', true)
                    ->update(['active' => false]);

                $totalAlertsClosed += (int) $closed;
                continue;
            }

            $alertTypeLabel = strtoupper($type);

            // Fetch Warehouse & Region Data
            $deviceForMail = $resolvedDeviceId ? \App\Models\Device::with('warehouse.region')->find($resolvedDeviceId) : null;
            $warehouseForMail = $deviceForMail?->warehouse ?: $resolveWarehouseForRow($row);
            $region = $warehouseForMail?->region;

            $managerEmail   = $warehouseForMail?->manager_email;
            $regionalEmail  = $region?->manager_email;
            $managerPhone   = $warehouseForMail?->manager_phone;
            $regionalPhone  = $region?->manager_phone;

            // === ROUTING CHECK ===
            $deviceTypeKey = strtolower($row['device_type'] ?? 'co2');
            $levelKey = strtolower($type);

            $routing = \App\Models\EmailRouting::where('device_type', $deviceTypeKey)
                ->where('level', $levelKey)
                ->first();

            $warehouseMail     = $routing ? (bool) $routing->warehouse_mail : true;
            $warehouseWA       = $routing?->warehouse_whatsapp ?? false;
            $regionalMail      = $routing?->regional_mail ?? false;
            $regionalWA        = $routing?->regional_whatsapp ?? false;
            $ccEmails = CcEmail::where('status', 1)
                ->pluck('email')
                ->toArray();



            // Build Mail Body
            $buildMailBody = function () use ($row, $resolvedDeviceId, $sensorDeviceId, $message, $alertTypeLabel, $resolveWarehouseForRow) {
                $deviceForMail = $resolvedDeviceId ? \App\Models\Device::with('warehouse.region')->find($resolvedDeviceId) : null;
                $warehouseForMail = $deviceForMail?->warehouse ?: $resolveWarehouseForRow($row);

                $regionName = $warehouseForMail?->region?->region_name ?: ($row['region'] ?? null);
                $regionCode = $warehouseForMail?->region?->region_code ?: ($row['region_code'] ?? null);
                $warehouseName = $warehouseForMail?->warehouse_name ?: ($row['warehouse'] ?? null);
                $warehouseCode = $warehouseForMail?->warehouse_code ?: ($row['warehouse_code'] ?? null);

                $recordedAt = isset($row['recorded_at'])
                    ? \Carbon\Carbon::parse($row['recorded_at'])->format('d M Y H:i')
                    : now()->format('d M Y H:i');

                $deviceName = $row['device_name'] ?? null;
                $deviceLine = $deviceName ? ($deviceName . ' (' . $sensorDeviceId . ')') : $sensorDeviceId;

                return "==============================\n"
                    . "ATS WAREHOUSE ALERT\n"
                    . "==============================\n"
                    . "ALERT TYPE : {$alertTypeLabel}\n"
                    . "MESSAGE : {$message}\n"
                    . "------------------------------\n"
                    . "DEVICE : {$deviceLine}\n"
                    . "DEVICE IP : " . ($row['device_ip'] ?? '-') . "\n"
                    . "DEVICE TYPE : " . ($row['device_type'] ?? '-') . "\n"
                    . "------------------------------\n"
                    . "REGION : " . ($regionName ?? '-') . " (" . ($regionCode ?? '-') . ")\n"
                    . "WAREHOUSE : " . ($warehouseName ?? '-') . " (" . ($warehouseCode ?? '-') . ")\n"
                    . "GODOWN : " . ($row['godown'] ?? '-') . "\n"
                    . "COMPARTMENT: " . ($row['compartment'] ?? '-') . "\n"
                    . "------------------------------\n"
                    . "READING : value=" . ($row['reading_value'] ?? '-')
                    . ", level=" . ($row['level'] ?? '-')
                    . ", status=" . ($row['status'] ?? '-') . "\n"
                    . "UNIT : " . ($row['unit'] ?? '-') . "\n"
                    . "RECORDED AT: {$recordedAt}\n"
                    . "------------------------------\n"
                    . "Generated by ATS Monitoring system Monitoring\n";
            };

            // Send Mail - Correct Flag Check
            $sendMail = function () use ($row, $alertTypeLabel, $managerEmail, $regionalEmail, $ccEmails, &$alertEmailsSent, $buildMailBody, $regionalMail): bool {
                $primaryRecipient = $managerEmail ?: ($regionalMail ? $regionalEmail : null);
                if (!$primaryRecipient) return false;

                try {
                    $mailBody = $buildMailBody();
                    $target = $row['warehouse'] ?? 'Warehouse';

                    $allCc = [];
                    // Only add regional if regional_mail flag is true
                    if ($regionalMail && $regionalEmail && $regionalEmail !== $primaryRecipient) {
                        $allCc[] = $regionalEmail;
                    }
                    // Add system CC emails
                    if (!empty($ccEmails)) {
                        $allCc = array_merge($allCc, $ccEmails);
                    }
                    $allCc = array_unique(array_filter($allCc));

                    \Illuminate\Support\Facades\Mail::raw(
                        $mailBody,
                        function ($m) use ($primaryRecipient, $alertTypeLabel, $target, $allCc) {
                            $m->to($primaryRecipient)
                                ->subject('Warehouse Alert [' . $alertTypeLabel . ']: ' . $target);

                            if (!empty($allCc)) {
                                $m->cc($allCc);
                            }
                        }
                    );

                    $alertEmailsSent++;
                    Log::info('Mail sent successfully', [
                        'to'       => $primaryRecipient,
                        'cc_count' => count($allCc),
                    ]);
                    return true;
                } catch (\Throwable $e) {
                    Log::error('MAIL SEND FAILED', ['error' => $e->getMessage()]);
                    return false;
                }
            };

            // Send WhatsApp - Independent flags
            $sendWhatsapp = function ($phone) use ($buildMailBody) {
                if (!$phone) return;

                $cleanPhone = preg_replace('/\D/', '', $phone);

                try {
                    $msg = $buildMailBody();
                    $response = Http::timeout(20)->asForm()->post(
                        'http://wapi.asgsinfosystem.in/wapp/v2/api/send',
                        [
                            'apikey' => env('WAPI_KEY'),
                            'mobile' => $cleanPhone,
                            'msg' => $msg,
                        ]
                    );

                    Log::info('WhatsApp sent', ['phone' => $cleanPhone]);
                } catch (\Throwable $e) {
                    Log::error('WhatsApp send failed', ['phone' => $cleanPhone, 'error' => $e->getMessage()]);
                }
            };

            // Alert Logic
            $alert = \App\Models\Alert::where('device_id', $sensorDeviceId)
                ->where('type', $type)
                ->where('active', true)
                ->first();

            if (!$alert) {
                $created = Alert::create([
                    'device_id' => $sensorDeviceId,
                    'reading_id' => $row['reading_id'] ?? null,
                    'type' => $type,
                    'message' => $message,
                    'last_email_at' => null,
                    'active' => true,
                    'alert_type' => $legacyAlertType,
                    'alert_value' => $row['reading_value'] ?? 0,
                ]);

                $totalAlertsCreated += $created ? 1 : 0;

                // Direct flag checks
                $emailSent = ($warehouseMail || $regionalMail) ? $sendMail() : false;
                if ($warehouseWA) {
                    $sendWhatsapp($managerPhone);
                }
                if ($regionalWA) {
                    $sendWhatsapp($regionalPhone);
                }

                if ($emailSent) {
                    $created->update(['last_email_at' => now()]);
                }
                continue;
            }

            // Existing alert throttle
            $last = $alert->last_email_at;
            $canSend = !$last || \Carbon\Carbon::parse($last)->addHour()->lte(now());

            if ($canSend) {
                $emailSent = ($warehouseMail || $regionalMail) ? $sendMail() : false;
                if ($warehouseWA) {
                    $sendWhatsapp($managerPhone);
                }
                if ($regionalWA) {
                    $sendWhatsapp($regionalPhone);
                }

                if ($emailSent) {
                    $alert->update(['last_email_at' => now()]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Readings stored successfully',
            'count' => count($rows),
            'alerting' => [
                'attempts' => $totalAlertAttempts,
                'alerts_created' => $totalAlertsCreated,
                'alerts_closed' => $totalAlertsClosed,
                'alert_emails_sent' => $alertEmailsSent,
            ],
        ]);
    }
}
