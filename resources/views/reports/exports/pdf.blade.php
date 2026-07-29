<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .meta { margin-bottom: 12px; }
        .meta b { display: inline-block; min-width: 110px; }
        .badge { padding: 3px 8px; border-radius: 999px; display: inline-block; font-size: 11px; }
        .normal { background: #16a34a; color: #fff; }
        .severe { background: #f59e0b; color: #000; }
        .critical { background: #dc2626; color: #fff; }
        .online { background: #16a34a; color: #fff; }
        .offline { background: #6b7280; color: #fff; }
    </style>
</head>
<body>
    <h2 style="margin:0 0 10px 0;">Warehouse Reports</h2>

    <div class="meta">
        <div><b>Report Type:</b>{{ $filters['report_type'] ?? 'reading' }}</div>
        <div><b>From:</b>{{ $filters['from_date'] ?? '-' }} &nbsp; <b>To:</b>{{ $filters['to_date'] ?? '-' }}</div>
        <div><b>Region:</b>{{ $filters['region_name'] ?? $filters['region_code'] ?? '-' }} &nbsp; <b>Warehouse:</b>{{ $filters['warehouse_name'] ?? $filters['warehouse_code'] ?? '-' }}</div>
    </div>

    @php($selected = $filters['selected_cols'] ?? null)
    @php(
        $selectedCols = $selected
            ? array_values(array_intersect(
                array_filter(array_map('trim', explode(',', (string)$selected))),
                ['date_time','region','region_code','warehouse','warehouse_code','device_name','device_code','device_type','device_ip','value','unit','level','status']
            ))
            : ['date_time','region','region_code','warehouse','warehouse_code','device_name','device_code','device_type','device_ip','value','unit','level','status']
    )

    @php($colMap = [
        'date_time' => 'Date & Time',
        'region' => 'Region',
        'region_code' => 'Region Code',
        'warehouse' => 'Warehouse',
        'warehouse_code' => 'Warehouse Code',
        'device_name' => 'Device Name',
        'device_code' => 'Device Code / Sensor Device ID',
        'device_type' => 'Device Type',
        'device_ip' => 'Device IP',
        'value' => 'Value',
        'unit' => 'Unit',
        'level' => 'Level',
        'status' => 'Status',
    ])

    <table>
        <thead>
            <tr>
                @foreach($selectedCols as $key)
                    <th>{{ $colMap[$key] ?? $key }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                @foreach($selectedCols as $key)
                    @if($key === 'date_time')
                        <td>{{ $row->recorded_at ? \Carbon\Carbon::parse($row->recorded_at)->format('d M Y H:i:s') : '-' }}</td>
                    @elseif($key === 'region')
                        <td>{{ $row->region ?: '-' }}</td>
                    @elseif($key === 'region_code')
                        <td>{{ $row->region_code ?: '-' }}</td>
                    @elseif($key === 'warehouse')
                        <td>{{ $row->warehouse ?: '-' }}</td>
                    @elseif($key === 'warehouse_code')
                        <td>{{ $row->warehouse_code ?: '-' }}</td>
                    @elseif($key === 'device_name')
                        <td>{{ $row->device_name ?: '-' }}</td>
                    @elseif($key === 'device_code')
                        <td>{{ $row->sensor_device_id ?: '-' }}</td>
                    @elseif($key === 'device_type')
                        <td>{{ $row->device_type ?: '-' }}</td>
                    @elseif($key === 'device_ip')
                        <td>{{ $row->device_ip ?: '-' }}</td>
                    @elseif($key === 'value')
                        <td>{{ $row->reading_value }}</td>
                    @elseif($key === 'unit')
                        <td>{{ $row->unit ?: '-' }}</td>
                    @elseif($key === 'level')
                        <td>
                            @php($lvl = \App\Models\Reading::normalizeLevel($row->reading_value, $row->level))
                            <span class="badge {{ $lvl === 'normal' ? 'normal' : ($lvl === 'severe' ? 'severe' : ($lvl === 'critical' ? 'critical' : 'offline')) }}">{{ ucfirst($lvl) }}</span>
                        </td>
                    @elseif($key === 'status')
                        <td>
                            @php($st = $row->status ?: 'offline')
                            <span class="badge {{ $st === 'online' ? 'online' : 'offline' }}">{{ ucfirst($st) }}</span>
                        </td>
                    @else
                        <td>-</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

</body>
</html>

