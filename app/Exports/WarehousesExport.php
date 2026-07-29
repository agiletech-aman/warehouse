<?php

namespace App\Exports;

use App\Models\Reading;
use App\Models\Warehouse;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WarehousesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    private $latestDeviceReadings;

    public function __construct(
        private string $search = '',
        private bool $activeOnly = false
    )
    {
    }

    public function query()
    {
        $query = Warehouse::with('region')->orderBy('warehouse_code');

        if ($this->activeOnly) {
            $query->activeInLast24Hours();
        }

        if ($this->search !== '') {
            $like = '%' . $this->search . '%';
            $query->where(function ($query) use ($like) {
                $query->where('warehouse_code', 'like', $like)
                    ->orWhere('warehouse_name', 'like', $like)
                    ->orWhere('manager_name', 'like', $like)
                    ->orWhere('manager_email', 'like', $like)
                    ->orWhere('manager_phone', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereHas('region', function ($regionQuery) use ($like) {
                        $regionQuery->where('region_code', 'like', $like)
                            ->orWhere('region_name', 'like', $like);
                    });
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Region',
            'Region Code',
            'Manager Name',
            'Manager Email',
            'Manager Phone',
            'Address',
            'City',
            'State',
            'Country',
            'Latitude',
            'Longitude',
            'CO2 Devices',
            'PH3 Devices',
            'Status',
        ];
    }

    public function map($warehouse): array
    {
        $deviceTypeCounts = $this->deviceTypeCounts($warehouse);

        return [
            $warehouse->warehouse_code,
            $warehouse->warehouse_name,
            optional($warehouse->region)->region_name ?: '-',
            optional($warehouse->region)->region_code ?: '-',
            $warehouse->manager_name ?: '-',
            $warehouse->manager_email ?: '-',
            $warehouse->manager_phone ?: '-',
            $warehouse->address ?: '-',
            $warehouse->city ?: '-',
            $warehouse->state ?: '-',
            $warehouse->country ?: '-',
            $warehouse->latitude,
            $warehouse->longitude,
            $deviceTypeCounts['CO2'],
            $deviceTypeCounts['PH3'],
            ucfirst((string) $warehouse->status),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->styleSheet($event, 'P');
            },
        ];
    }

    private function deviceTypeCounts(Warehouse $warehouse): array
    {
        $this->latestDeviceReadings ??= Reading::query()
            ->whereIn('id', Reading::latestIdsPerSensor())
            ->get(['id', 'warehouse', 'warehouse_code', 'device_type']);

        $warehouseCode = strtolower(trim((string) $warehouse->warehouse_code));
        $warehouseName = strtolower(trim((string) $warehouse->warehouse_name));

        $warehouseReadings = $this->latestDeviceReadings->filter(function (Reading $reading) use ($warehouseCode, $warehouseName) {
            $readingCode = strtolower(trim((string) $reading->warehouse_code));
            $readingName = strtolower(trim((string) $reading->warehouse));

            return ($warehouseCode !== '' && $readingCode === $warehouseCode)
                || ($warehouseName !== '' && $readingName === $warehouseName);
        });

        return [
            'CO2' => $warehouseReadings->filter(
                fn (Reading $reading) => $this->normalizedDeviceType($reading->device_type) === 'CO2'
            )->count(),
            'PH3' => $warehouseReadings->filter(
                fn (Reading $reading) => $this->normalizedDeviceType($reading->device_type) === 'PH3'
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

    private function styleSheet(AfterSheet $event, string $statusColumn): void
    {
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

        for ($row = 2; $row <= $highestRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$highestColumn}{$row}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F8FAFC');
            }

            $this->styleStatusCell($sheet, "{$statusColumn}{$row}");
        }
    }

    private function styleStatusCell($sheet, string $cell): void
    {
        $status = strtolower((string) $sheet->getCell($cell)->getValue());
        $colors = $status === 'active'
            ? ['fill' => 'DCFCE7', 'font' => '166534']
            : ['fill' => 'FEE2E2', 'font' => '991B1B'];

        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $colors['font']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['fill']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }
}
