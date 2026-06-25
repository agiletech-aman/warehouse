<?php

namespace App\Exports;

use App\Models\Reading;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DevicesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    public function __construct(private array $filters = [])
    {
    }

    public function query()
    {
        $query = Reading::whereIn('id', $this->latestReadingIds())
            ->latest('recorded_at')
            ->latest('id');

        $selectedRegion = trim((string) ($this->filters['region_code'] ?? ''));
        $selectedWarehouse = trim((string) ($this->filters['warehouse_code'] ?? ''));

        if ($selectedRegion !== '') {
            $query->where(function ($query) use ($selectedRegion) {
                $query->where('region_code', $selectedRegion)
                    ->orWhere('region', $selectedRegion);
            });
        }

        if ($selectedWarehouse !== '') {
            $query->where(function ($query) use ($selectedWarehouse) {
                $query->where('warehouse_code', $selectedWarehouse)
                    ->orWhere('warehouse', $selectedWarehouse);
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
            'Warehouse',
            'Warehouse Code',
            'Type',
            'Location',
            'Latest Reading',
            'Unit',
            'Level',
            'Status',
            'Recorded At',
        ];
    }

    public function map($reading): array
    {
        return [
            $reading->sensor_device_id ?: '-',
            $reading->device_name ?: '-',
            $reading->region ?: '-',
            $reading->region_code ?: '-',
            $reading->warehouse ?: '-',
            $reading->warehouse_code ?: '-',
            $reading->device_type ?: '-',
            trim(($reading->godown ?: '-') . ($reading->compartment ? ' / ' . $reading->compartment : '')),
            $reading->reading_value,
            $reading->unit ?: '-',
            ucfirst((string) ($reading->level ?: 'normal')),
            ucfirst((string) ($reading->status ?: 'offline')),
            optional($reading->recorded_at)->format('Y-m-d H:i:s') ?: '-',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->styleSheet($event);
            },
        ];
    }

    private function latestReadingIds()
    {
        return Reading::select(DB::raw('MAX(id) as id'))
            ->whereNotNull('sensor_device_id')
            ->groupBy('sensor_device_id');
    }

    private function styleSheet(AfterSheet $event): void
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

            $this->styleLevelCell($sheet, "K{$row}");
            $this->styleStatusCell($sheet, "L{$row}");
        }
    }

    private function styleLevelCell($sheet, string $cell): void
    {
        $level = strtolower((string) $sheet->getCell($cell)->getValue());
        $colors = match ($level) {
            'critical' => ['fill' => 'FEE2E2', 'font' => '991B1B'],
            'severe' => ['fill' => 'FEF3C7', 'font' => '92400E'],
            default => ['fill' => 'DCFCE7', 'font' => '166534'],
        };

        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $colors['font']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['fill']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    private function styleStatusCell($sheet, string $cell): void
    {
        $status = strtolower((string) $sheet->getCell($cell)->getValue());
        $colors = $status === 'online'
            ? ['fill' => 'DCFCE7', 'font' => '166534']
            : ['fill' => 'E5E7EB', 'font' => '374151'];

        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $colors['font']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['fill']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }
}
