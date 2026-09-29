<?php

namespace App\Console\Commands;

use App\Support\FnsDetectionLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncFnsDetectionLocations extends Command
{
    protected $signature = 'fns-detections:sync-locations {--dry-run : Show rows that would be updated without changing them}';

    protected $description = 'Sync godown and compartment columns from encoded FNS camera names';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $totalUpdated = 0;

        foreach (['fns_detections', 'fns_detections_02'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $updatedInTable = 0;

            DB::table($table)
                ->select(['id', 'camera_name', 'godown', 'compartment'])
                ->whereNotNull('camera_name')
                ->where('camera_name', 'like', 'G%')
                ->chunkById(500, function ($detections) use ($table, $dryRun, &$updatedInTable): void {
                    $groups = [];

                    foreach ($detections as $detection) {
                        $location = FnsDetectionLocation::fromCameraName($detection->camera_name);

                        if (! $location
                            || ($detection->godown === $location['godown']
                                && $detection->compartment === $location['compartment'])) {
                            continue;
                        }

                        $key = $location['godown'] . "\0" . $location['compartment'];
                        $groups[$key]['location'] = $location;
                        $groups[$key]['ids'][] = $detection->id;
                    }

                    foreach ($groups as $group) {
                        $count = count($group['ids']);
                        $updatedInTable += $count;

                        if (! $dryRun) {
                            DB::table($table)
                                ->whereIn('id', $group['ids'])
                                ->update($group['location']);
                        }
                    }
                }, 'id');

            $totalUpdated += $updatedInTable;
            $this->line(($dryRun ? 'Would update ' : 'Updated ')
                . $updatedInTable . " row(s) in {$table}.");
        }

        $this->info(($dryRun ? 'Rows to update: ' : 'Total rows updated: ') . $totalUpdated . '.');

        return self::SUCCESS;
    }
}
