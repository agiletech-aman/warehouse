<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CameraAlertService;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;

class CameraAlertController extends Controller
{
    private function build(
        Request $request,
        CameraAlertService $service,
        string $type,
        bool $allPages = false
    ) {
        $user           = auth()->user();
        $warehouseNames = $user->getAccessibleWarehouseNames();

        $displayPageSize = 20;
        $currentPage     = $allPages ? 1 : (int) $request->get('page', 1);
        $location        = $request->get('location');
        $state           = $request->get('state');
        $fromDate        = $request->get('from_date');
        $toDate          = $request->get('to_date');

        /* ── ALERTS DATA ─────────────────────────────────────── */
        // The upstream API's `alertType` query param does not actually filter
        // (it returns alerts of every type), so we always pull a large batch
        // and filter by the real per-row alertType ourselves.
        $response   = $service->fetch(1, 9999, $location, $type, $state);
        $collection = collect($response['data'] ?? []);

        /* ── FILTER BY ACCESSIBLE WAREHOUSES ─────────────────── */
        $collection = collect(
            $this->filterByAccessibleWarehouses($collection->toArray(), $warehouseNames)
        );

        /* ── FILTER BY ACTUAL ALERT TYPE ──────────────────────── */
        $collection = $collection->filter(
            fn($row) => str_contains(strtolower($row['alertType'] ?? ''), strtolower($type))
        );

        /* ── DATE FILTER ──────────────────────────────────────── */
        if ($fromDate && $toDate) {
            $from = Carbon::parse($fromDate)->startOfDay();
            $to   = Carbon::parse($toDate)->endOfDay();

            $collection = $collection->filter(function ($row) use ($from, $to) {
                if (empty($row['alertDateTime'])) return false;
                return Carbon::parse($row['alertDateTime'])->between($from, $to);
            });
        }

        $filtered   = $collection->sortByDesc('alertDateTime')->values();
        $totalCount = $filtered->count();

        $pageSize = $allPages ? max($totalCount, 1) : $displayPageSize;
        $pageItems = $allPages
            ? $filtered
            : $filtered->forPage($currentPage, $displayPageSize)->values();

        $alerts = new LengthAwarePaginator(
            $pageItems,
            $totalCount,
            $pageSize,
            $currentPage,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        /* ── DROPDOWNS ────────────────────────────────────────── */
        $dropdown  = $service->getLocationsAndStates();
        $locations = $dropdown['locations'];
        $states    = $dropdown['states'];

        /* ── FILTER LOCATIONS DROPDOWN FOR RO/WO ─────────────── */
        if ($user->isRegionOfficer() || $user->isWarehouseOfficer()) {
            $locations = array_values(array_intersect($locations, $warehouseNames));
        }

        return [$alerts, $totalCount, $locations, $states];
    }

    /**
     * Filter alerts by accessible warehouses.
     * SuperAdmin and Admin see everything. RO and WO see only their warehouses.
     */
    private function filterByAccessibleWarehouses(array $data, array $warehouseNames): array
    {
        $user = auth()->user();

        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return $data;
        }

        return collect($data)
            ->filter(function ($item) use ($warehouseNames) {
                $location = $item['locationName'] ?? $item['location'] ?? null;
                return $location && in_array($location, $warehouseNames);
            })
            ->values()
            ->toArray();
    }

    // ── Alert type views ────────────────────────────────────────

    public function fire(Request $request, CameraAlertService $service)
    {
        [$alerts, $total, $locations, $states] =
            $this->build($request, $service, 'fire');

        return view('alerts.fire', compact('alerts', 'total', 'locations', 'states'));
    }

    public function smoke(Request $request, CameraAlertService $service)
    {
        [$alerts, $total, $locations, $states] =
            $this->build($request, $service, 'smoke');

        return view('alerts.smoke', compact('alerts', 'total', 'locations', 'states'));
    }

    public function rodent(Request $request, CameraAlertService $service)
    {
        [$alerts, $total, $locations, $states] =
            $this->build($request, $service, 'rodent');

        return view('alerts.rodent', compact('alerts', 'total', 'locations', 'states'));
    }

    // ── AJAX: locations for a given state ───────────────────────

    public function locationsByState(Request $request, CameraAlertService $service)
    {
        $state = $request->get('state', '');

        if (empty($state)) {
            return response()->json([]);
        }

        return response()->json($service->getLocationsByState($state));
    }

    // ── Excel Export (shared helper) ────────────────────────────

    private function exportExcel(array $rows, string $filename, string $alertType)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($alertType) . ' Alerts');

        // ── Header row styling
        $headerStyle = [
            'font' => [
                'bold'  => true,
                'size'  => 11,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType'   => 'solid',
                'startColor' => ['argb' => 'FF4B5563'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        // ── Data row styling (alternating)
        $dataStyleEven = [
            'fill' => [
                'fillType'   => 'solid',
                'startColor' => ['argb' => 'FFF9FAFB'],
            ],
        ];

        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFE2E8F0'],
                ],
            ],
        ];

        // ── Write headers
        $headers = ['#', 'Date & Time', 'Camera Name', 'Location', 'Alert Type'];
        foreach ($headers as $colIndex => $heading) {
            $col  = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cell = $col . '1';
            $sheet->setCellValue($cell, $heading);
            $sheet->getStyle($cell)->applyFromArray($headerStyle);
        }
        $sheet->getRowDimension(1)->setRowHeight(32);

        // ── Write data rows
        foreach ($rows as $i => $row) {
            $r = $i + 2;

            $sheet->setCellValue("A{$r}", $i + 1);
            $sheet->setCellValue("B{$r}", Carbon::parse($row['alertDateTime'])->format('d-m-Y H:i:s'));
            $sheet->setCellValue("C{$r}", $row['cameraName']   ?? '-');
            $sheet->setCellValue("D{$r}", $row['locationName'] ?? '-');
            $sheet->setCellValue("E{$r}", ucfirst($alertType));

            // Center # and Alert Type columns
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );
            $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );

            // Alternating row background
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$r}:E{$r}")->applyFromArray($dataStyleEven);
            }

            $sheet->getRowDimension($r)->setRowHeight(24);
        }

        // ── Apply borders to full table
        $lastRow = count($rows) + 1;
        if ($lastRow >= 2) {
            $sheet->getStyle("A1:E{$lastRow}")->applyFromArray($borderStyle);
        }

        // ── Column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(16);

        // ── Freeze header row
        $sheet->freezePane('A2');

        // ── Auto-filter
        $sheet->setAutoFilter("A1:E1");

        // ── Write to temp file and download
        $tmpFile = tempnam(sys_get_temp_dir(), 'alert_export_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpFile);

        return response()->download($tmpFile, $filename)->deleteFileAfterSend(true);
    }

    // ── Export actions ──────────────────────────────────────────

    public function exportSmoke(Request $request, CameraAlertService $service)
    {
        [$alerts] = $this->build($request, $service, 'smoke', true);
        return $this->exportExcel(
            $alerts->getCollection()->toArray(),
            'smoke_alerts_' . now()->format('Ymd_His') . '.xlsx',
            'smoke'
        );
    }

    public function exportFire(Request $request, CameraAlertService $service)
    {
        [$alerts] = $this->build($request, $service, 'fire', true);
        return $this->exportExcel(
            $alerts->getCollection()->toArray(),
            'fire_alerts_' . now()->format('Ymd_His') . '.xlsx',
            'fire'
        );
    }

    public function exportRodent(Request $request, CameraAlertService $service)
    {
        [$alerts] = $this->build($request, $service, 'rodent', true);
        return $this->exportExcel(
            $alerts->getCollection()->toArray(),
            'rodent_alerts_' . now()->format('Ymd_His') . '.xlsx',
            'rodent'
        );
    }
}
