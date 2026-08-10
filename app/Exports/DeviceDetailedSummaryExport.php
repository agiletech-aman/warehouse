<?php

namespace App\Exports;

use App\Models\Reading;
use App\Models\DeviceLatestStatus;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DeviceDetailedSummaryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = DeviceLatestStatus::query();

        $region = trim((string) ($this->filters['region_code'] ?? ''));
        $warehouse = trim((string) ($this->filters['warehouse_code'] ?? ''));
        $status = strtolower(trim((string) ($this->filters['status'] ?? '')));

        if ($region !== '') {
            $query->where(function ($query) use ($region) {
                $query->where('region_code', $region)->orWhere('region', $region);
            });
        }

        if ($warehouse !== '') {
            $query->where(function ($query) use ($warehouse) {
                $query->where('warehouse_code', $warehouse)->orWhere('warehouse', $warehouse);
            });
        }

        if (in_array($status, ['online', 'offline'], true)) {
            $query->where('status', $status);
        }

        return $query
            ->get([
                'device_type',
                'status',
                'region',
                'region_code',
                'warehouse',
                'warehouse_code',
            ])
            ->groupBy(function (DeviceLatestStatus $reading) {
                $code = trim((string) $reading->warehouse_code);
                $name = trim((string) $reading->warehouse);

                return $code !== '' ? 'code:'.$code : 'name:'.($name !== '' ? $name : 'unassigned');
            })
            ->map(function (Collection $readings) {
                /** @var DeviceLatestStatus $warehouse */
                $warehouse = $readings->first();
                $name = trim((string) $warehouse->warehouse);
                $code = trim((string) $warehouse->warehouse_code);
                $regionName = trim((string) $warehouse->region);
                $regionCode = trim((string) $warehouse->region_code);
                $overall = $this->statusCounts($readings);
                $co2 = $this->statusCounts($readings->filter(
                    fn (DeviceLatestStatus $reading) => $this->normalizedDeviceType($reading->device_type) === 'CO2'
                ));
                $ph3 = $this->statusCounts($readings->filter(
                    fn (DeviceLatestStatus $reading) => $this->normalizedDeviceType($reading->device_type) === 'PH3'
                ));

                return [
                    $name !== '' ? $name : ($code !== '' ? $code : 'Unassigned'),
                    $code !== '' ? $code : '-',
                    $regionName !== '' ? $regionName : ($regionCode !== '' ? $regionCode : '-'),
                    $regionCode !== '' ? $regionCode : '-',
                    $overall['total'],
                    $overall['online'],
                    $overall['offline'],
                    $co2['total'],
                    $co2['online'],
                    $co2['offline'],
                    $ph3['total'],
                    $ph3['online'],
                    $ph3['offline'],
                ];
            })
            ->sortBy(fn (array $row) => $row[0], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function headings(): array
    {
        return [
            'Warehouse',
            'Warehouse Code',
            'Region',
            'Region Code',
            'Overall Total',
            'Overall Online',
            'Overall Offline',
            'CO2 Total',
            'CO2 Online',
            'CO2 Offline',
            'PH3 Total',
            'PH3 Online',
            'PH3 Offline',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            },
        ];
    }

    private function statusCounts(Collection $readings): array
    {
        return [
            'total' => $readings->count(),
            'online' => $readings->filter(
                fn (DeviceLatestStatus $reading) => strtolower(trim((string) $reading->status)) === 'online'
            )->count(),
            'offline' => $readings->filter(
                fn (DeviceLatestStatus $reading) => strtolower(trim((string) $reading->status)) === 'offline'
            )->count(),
        ];
    }

    private function normalizedDeviceType(?string $deviceType): string
    {
        return strtoupper(str_replace(
            [' ', '-', '_', '₂', '₃'],
            ['', '', '', '2', '3'],
            trim((string) $deviceType)
        ));
    }
}
