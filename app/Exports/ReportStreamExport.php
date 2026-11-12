<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ReportStreamExport
{
    public static function download(ReportExport $export, string $fileName)
    {
        set_time_limit(0);

        $path = storage_path('app/' . uniqid('report_') . '.xlsx');

        $options = new Options();
        $options->setColumnWidthForRange(22, 1, count(ReportExport::COLUMNS));

        $writer = new Writer($options);
        $writer->openToFile($path);

        $headerStyle = (new Style())
            ->withFontBold(true)
            ->withFontColor(Color::WHITE)
            ->withBackgroundColor("1D4ED8");

        $headerRow = new Row(array_map(fn ($h) => \OpenSpout\Common\Entity\Cell::fromValue($h, $headerStyle), $export->headings()));
        $writer->addRow($headerRow);

        // unbuffered query: rows ek-ek karke aayengi, poora result memory mein nahi
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        try {
            foreach ($export->query()->cursor() as $row) {
                $writer->addRow(Row::fromValues($export->map($row)));
            }
        } finally {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        $writer->close();

        return response()->download($path, $fileName . '.xlsx')->deleteFileAfterSend(true);
    }
}
