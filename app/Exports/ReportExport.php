<?php

namespace App\Exports;

use App\Models\Reading;
use Carbon\Carbon;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ReportExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths, ShouldAutoSize
{
    public function __construct(private array $filters = [])
    {
        $this->filters = $filters;
    }

    private function selectedColumns(): array
    {
        $raw = $this->filters['selected_cols'] ?? null;
        if (!$raw) {
            return [
                'date_time','region','region_code','warehouse','warehouse_code',
                'device_name','device_code','device_type','device_ip','value','unit','level','status'
            ];
        }

        $selected = array_filter(array_map('trim', explode(',', (string)$raw)));
        $allowed = [
            'date_time','region','region_code','warehouse','warehouse_code',
            'device_name','device_code','device_type','device_ip','value','unit','level','status'
        ];

        return array_values(array_intersect($selected, $allowed));
    }


    public function headings(): array
    {
        $selected = $this->selectedColumns();

        $map = [
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
        ];

        return array_values(array_filter(array_map(function ($key) use ($map) {
            return $map[$key] ?? null;
        }, $selected)));
    }


    public function map($row): array
    {
        $selected = $this->selectedColumns();

        $values = [
            'date_time' => $row->recorded_at ? Carbon::parse($row->recorded_at)->format('d M Y H:i') : null,
            'region' => $row->region ?: '-',
            'region_code' => $row->region_code ?: '-',
            'warehouse' => $row->warehouse ?: '-',
            'warehouse_code' => $row->warehouse_code ?: '-',
            'device_name' => $row->device_name ?: '-',
            'device_code' => $row->sensor_device_id ?: '-',
            'device_type' => $row->device_type ?: '-',
            'device_ip' => $row->device_ip ?: '-',
            'value' => $row->reading_value,
            'unit' => $row->unit ?: '-',
            'level' => $row->level ?: 'normal',
            'status' => $row->status ?: 'offline',
        ];

        return array_values(array_filter(array_map(function ($key) use ($values) {
            return $values[$key] ?? null;
        }, $selected), function ($v) {
            return $v !== null;
        }));
    }


    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 18,
            'C' => 15,
            'D' => 22,
            'E' => 18,
            'F' => 22,
            'G' => 26,
            'H' => 18,
            'I' => 18,
            'J' => 12,
            'K' => 12,
            'L' => 12,
            'M' => 12,
        ];
    }

    public function query()
    {
        $q = Reading::query();

        if (!empty($this->filters['from_date'])) {
            $q->where('recorded_at', '>=', Carbon::parse($this->filters['from_date'])->format('Y-m-d') . ' 00:00:00');
        }
        if (!empty($this->filters['to_date'])) {
            $q->where('recorded_at', '<=', Carbon::parse($this->filters['to_date'])->format('Y-m-d') . ' 23:59:59');
        }

        if (!empty($this->filters['region_code'])) {
            $q->where('region_code', $this->filters['region_code']);
        }
        if (!empty($this->filters['region_name'])) {
            $q->where('region', $this->filters['region_name']);
        }

        if (!empty($this->filters['warehouse_code'])) {
            $q->where('warehouse_code', $this->filters['warehouse_code']);
        }
        if (!empty($this->filters['warehouse_name'])) {
            $q->where('warehouse', $this->filters['warehouse_name']);
        }

        if (!empty($this->filters['device_type'])) {
            $q->where('device_type', $this->filters['device_type']);
        }

        if (!empty($this->filters['device_code'])) {
            $q->where('sensor_device_id', $this->filters['device_code']);
        }

        if (!empty($this->filters['device_name'])) {
            $q->where('device_name', $this->filters['device_name']);
        }

        if (!empty($this->filters['status'])) {
            $q->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['level'])) {
            $q->where('level', $this->filters['level']);
        }

        // Report type adjustments: filter only; keep unified structure.
        $reportType = strtolower((string) ($this->filters['report_type'] ?? 'reading'));
        if ($reportType === 'offline_device') {
            $q->where('status', 'offline');
        } elseif ($reportType === 'severe_alert') {
            $q->where('level', 'severe');
        } elseif ($reportType === 'critical_alert') {
            $q->where('level', 'critical');
        }

        return $q
            ->latest('recorded_at')
            ->select([
                'recorded_at',
                'region',
                'region_code',
                'warehouse',
                'warehouse_code',
                'device_name',
                'sensor_device_id',
                'device_type',
                'device_ip',
                'reading_value',
                'unit',
                'level',
                'status',
            ]);
    }
}

