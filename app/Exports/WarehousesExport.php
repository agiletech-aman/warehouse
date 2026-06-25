<?php

namespace App\Exports;

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
    public function query()
    {
        return Warehouse::with('region')->orderBy('warehouse_code');
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
            'Status',
        ];
    }

    public function map($warehouse): array
    {
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
            ucfirst((string) $warehouse->status),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->styleSheet($event, 'N');
            },
        ];
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
