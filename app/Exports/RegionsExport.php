<?php

namespace App\Exports;

use App\Models\Region;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RegionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    public function __construct(private string $search = '')
    {
    }

    public function query()
    {
        $query = Region::query()->orderBy('region_code');

        if ($this->search !== '') {
            $like = '%' . $this->search . '%';
            $query->where(function ($query) use ($like) {
                $query->where('region_code', 'like', $like)
                    ->orWhere('region_name', 'like', $like)
                    ->orWhere('manager_name', 'like', $like)
                    ->orWhere('manager_email', 'like', $like)
                    ->orWhere('manager_phone', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Manager Name',
            'Manager Email',
            'Manager Phone',
            'Status',
        ];
    }

    public function map($region): array
    {
        return [
            $region->region_code,
            $region->region_name,
            $region->manager_name ?: '-',
            $region->manager_email ?: '-',
            $region->manager_phone ?: '-',
            ucfirst((string) $region->status),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->styleSheet($event, 'F');
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
