<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Reading;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function index()
    {
        $readings = Reading::with('device')->latest()->paginate(10);

        return view('readings.index', compact('readings'));
    }

    public function create()
    {
        // UI: readings can still be manually linked to a device (optional).
        // Import removed since IoT store flow no longer resolves device FK.
        $devices = \App\Models\Device::latest()->get();

        return view('readings.create', compact('devices'));
    }

    public function store(Request $request)
    {
        $rows = [];

        foreach ($request->readings as $reading) {
            $rows[] = [
                'key' => $request->key,

                // Store IoT sensor identifier directly as string (NOT FK).
                // Your payload uses device_id like: CO2_<ip>_<port>_<godown>.
                'sensor_device_id' => $reading['device_id'] ?? null,

                // FK is nullable now (migration added). Keep it NULL by default.
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

                // level: normal/warning/critical
                'level' => strtolower($reading['level'] ?? 'normal'),

                // status: online/offline
                'status' => strtolower($reading['status'] ?? 'online'),

                'recorded_at' => $reading['recorded_at'] ?? now(),

                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Reading::insert($rows);

        // Throttled alerting (email every 1 hour max per device+type)
        // Also closes alert when status becomes normal.
        $totalAlertsCreated = 0;
        $totalAlertsClosed = 0;
        $totalAlertAttempts = 0;
        $unmappedSensorDeviceIds = [];

        // IoT device resolution helpers
        $resolveDeviceId = function (?string $sensorDeviceId, ?string $sensorIp, $sensorPort) {
            if (!$sensorDeviceId) {
                return null;
            }

            // 0) If payload sends actual device id (numeric)
            if (is_numeric($sensorDeviceId)) {
                $byId = Device::where('id', (int) $sensorDeviceId)->first();
                if ($byId) {
                    return $byId->id;
                }
            }

            // 1) Exact match by device_code
            $byCode = Device::where('device_code', $sensorDeviceId)->first();
            if ($byCode) {
                return $byCode->id;
            }

            // 2) Parse CO2_<ip>_<port>_<godown>
            if (str_contains($sensorDeviceId, '_')) {
                $parts = explode('_', $sensorDeviceId);
                // [type, ip, port, godown...]
                $parsedIp = $parts[1] ?? null;
                $parsedPort = $parts[2] ?? null;

                $lookupIp = $sensorIp ?: $parsedIp;
                $lookupPort = $sensorPort ?: $parsedPort;

                if ($lookupIp) {
                    // devices table stores IP in ip_address (no device_ip / port columns)
                    $byIp = Device::where('ip_address', $lookupIp)->first();
                    if ($byIp) {
                        return $byIp->id;
                    }
                }
            }

            return null;
        };

        foreach ($rows as $row) {
            $sensorDeviceId = $row['sensor_device_id'];
            $sensorIp = $row['device_ip'];
            $sensorPort = $row['port'];

            $deviceId = $row['device_id']; // currently null in IoT store flow
            if (!$deviceId) {
                $deviceId = $resolveDeviceId($sensorDeviceId, $sensorIp, $sensorPort);
            }

            if (!$deviceId) {
                if ($sensorDeviceId && count($unmappedSensorDeviceIds) < 10) {
                    $unmappedSensorDeviceIds[] = $sensorDeviceId;
                }
                continue;
            }

            // If reading comes from this device and device is online => set devices.status online
            $status = $row['status'] ?? 'online';
            if (strtolower($status) === 'online') {
                Device::where('id', $deviceId)->update(['status' => 'online']);
            }

            $level = $row['level'] ?? 'normal';
            $totalAlertAttempts++;

            // Determine alert type + message
            $type = null;
            $message = null;

            if ($level === 'critical') {
                $type = 'critical';
                $message = 'Critical Alert: Immediate action required.';
            } elseif ($level === 'warning') {
                $type = 'warning';
                $message = 'Warning: Parameter out of range.';
            }

            // device_offline handling (optional)
            if ($type === null && $status === 'offline') {
                $type = 'warning';
                $message = 'Device is OFFLINE';
            }

            // If normal condition: close any active alerts for this device+warning/critical.
            if ($type === null) {
                $closed = Alert::where('device_id', $deviceId)
                    ->whereIn('type', ['warning', 'critical'])
                    ->where('active', true)
                    ->update(['active' => false]);

                $totalAlertsClosed += (int) $closed;
                continue;
            }

            // Throttle by last_email_at for (device_id, type)
            $alert = \App\Models\Alert::where('device_id', $deviceId)
                ->where('type', $type)
                ->where('active', true)
                ->first();

            $alertTypeLabel = $type === 'critical' ? 'CRITICAL' : 'WARNING';

            $buildMailBody = function () use ($row, $deviceId, $sensorDeviceId, $message, $alertTypeLabel, $type) {
                $deviceForMail = \App\Models\Device::with('warehouse.region')->find($deviceId);
                $warehouseForMail = $deviceForMail?->warehouse;

                $regionName = $warehouseForMail?->region?->region_name;
                $regionCode = $warehouseForMail?->region?->region_code;
                $warehouseName = $warehouseForMail?->warehouse_name;
                $warehouseCode = $warehouseForMail?->warehouse_code;

                $sensorId = $sensorDeviceId;

                $recordedAt = isset($row['recorded_at'])
                    ? \Carbon\Carbon::parse($row['recorded_at'])->format('d M Y H:i')
                    : now()->format('d M Y H:i');

                $deviceName = $row['device_name'] ?? null;
                $deviceLine = $deviceName ? ($deviceName . ' (' . $sensorId . ')') : $sensorId;

                return "==============================\n"
                    . "ATS WAREHOUSE ALERT\n"
                    . "==============================\n"
                    . "ALERT TYPE : {$alertTypeLabel}\n"
                    . "MESSAGE    : {$message}\n"
                    . "------------------------------\n"
                    . "DEVICE     : {$deviceLine}\n"
                    . "DEVICE IP  : " . ($row['device_ip'] ?? '-') . "\n"
                    . "PORT       : " . ($row['port'] ?? '-') . "\n"
                    . "------------------------------\n"
                    . "REGION     : " . ($regionName ?? '-') . " (" . ($regionCode ?? '-') . ")\n"
                    . "WAREHOUSE  : " . ($warehouseName ?? '-') . " (" . ($warehouseCode ?? '-') . ")\n"
                    . "GODOWN      : " . ($row['godown'] ?? '-') . "\n"
                    . "COMPARTMENT: " . ($row['compartment'] ?? '-') . "\n"
                    . "------------------------------\n"
                    . "READING    : value=" . ($row['reading_value'] ?? '-')
                    . ", level=" . ($row['level'] ?? '-')
                    . ", status=" . ($row['status'] ?? '-') . "\n"
                    . "UNIT       : " . ($row['unit'] ?? '-') . "\n"
                    . "RECORDED AT: {$recordedAt}\n"
                    . "------------------------------\n"
                    . "Generated by ATS IoT Monitoring\n";
            };

            $sendMail = function () use ($row, $deviceId, $alertTypeLabel, $buildMailBody) {
                $deviceForMail = \App\Models\Device::with('warehouse')->find($deviceId);
                $managerEmail = $deviceForMail?->warehouse?->manager_email;

                if (!$managerEmail) {
                    return;
                }

                try {
                    $mailBody = $buildMailBody();

                    $warehouseName = $deviceForMail?->warehouse?->warehouse_name;
                    $warehouseCode = $deviceForMail?->warehouse?->warehouse_code;
                    $target = ($warehouseCode && $warehouseName)
                        ? "{$warehouseName} ({$warehouseCode})"
                        : ($warehouseName ?: ($warehouseCode ?: 'Warehouse'));

                    \Illuminate\Support\Facades\Mail::raw(
                        $mailBody,
                        function ($m) use ($managerEmail, $alertTypeLabel, $target) {
                            $m->to($managerEmail)->subject('Warehouse Alert [' . $alertTypeLabel . ']: ' . $target);
                        }
                    );
                } catch (\Throwable $e) {
                    // swallow to avoid breaking ingestion
                }
            };

            if (!$alert) {
                $created = Alert::create([
                    'device_id' => $deviceId,
                    'type' => $type,
                    'message' => $message,
                    'last_email_at' => now(),
                    'active' => true,
                    // legacy columns
                    'alert_type' => $type === 'critical' ? 'high_co2' : 'high_phosphorus',
                    'alert_value' => $row['reading_value'] ?? 0,
                ]);

                $totalAlertsCreated += $created ? 1 : 0;

                $sendMail();
            } else {
                $last = $alert->last_email_at;
                $canSend = !$last || \Carbon\Carbon::parse($last)->addHour()->lte(now());

                if ($canSend) {
                    $sendMail();
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
                'unmapped_sensor_device_ids_sample' => array_values(array_slice($unmappedSensorDeviceIds, 0, 10)),
            ],
        ]);
    }

    public function show(Reading $reading)
    {
        $reading->load('device');

        return view('readings.show', compact('reading'));
    }

    public function edit(Reading $reading)
    {
        $devices = \App\Models\Device::latest()->get();

        return view('readings.edit', compact('reading', 'devices'));
    }

    public function update(Request $request, Reading $reading)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'reading_value' => 'required|numeric',
            'unit' => 'required|string|max:20',
            'status' => 'required|in:normal,warning,critical',
            'recorded_at' => 'nullable|date',
        ]);

        $reading->update($request->only([
            'device_id',
            'reading_value',
            'unit',
            'status',
            'recorded_at',
        ]));

        return redirect()->route('readings.index')->with('success', 'Reading updated successfully.');
    }

    public function destroy(Reading $reading)
    {
        $reading->delete();

        return redirect()->route('readings.index')->with('success', 'Reading deleted successfully.');
    }
}

