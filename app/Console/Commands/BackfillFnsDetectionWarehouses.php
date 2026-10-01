<?php

namespace App\Console\Commands;

use App\Support\FnsDetectionWarehouseLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillFnsDetectionWarehouses extends Command
{
    protected $signature = 'fns-detections:backfill-warehouses {--dry-run : Report changes without updating rows}';

    protected $description = 'Backfill missing FNS detection warehouse codes from camera IP addresses';

    public function handle(): int
    {
        if (! Schema::hasTable('fns_detections_02')) {
            $this->error('Table fns_detections_02 does not exist.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $unmapped = [];

        DB::table('fns_detections_02')
            ->select(['id', 'camera_ip'])
            ->whereNull('warehouse_code')
            ->chunkById(1000, function ($rows) use ($dryRun, &$updated, &$unmapped): void {
                foreach ($rows as $row) {
                    $warehouseCode = FnsDetectionWarehouseLookup::forCameraIp($row->camera_ip);

                    if (! $warehouseCode) {
                        $unmapped[(string) $row->camera_ip] = true;
                        continue;
                    }

                    $updated++;
                    if (! $dryRun) {
                        DB::table('fns_detections_02')
                            ->where('id', $row->id)
                            ->whereNull('warehouse_code')
                            ->update(['warehouse_code' => $warehouseCode]);
                    }
                }
            }, 'id');

        $this->info(($dryRun ? 'Rows that would be backfilled: ' : 'Rows backfilled: ') . $updated);
        $this->info('Camera IPs without a warehouse mapping: ' . count($unmapped));
        foreach (array_keys($unmapped) as $ip) {
            $this->line($ip === '' ? '(empty camera_ip)' : $ip);
        }

        return self::SUCCESS;
    }
}
