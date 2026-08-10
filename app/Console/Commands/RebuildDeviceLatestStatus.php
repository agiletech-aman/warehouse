<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Services\DeviceLatestStatusService;
use Illuminate\Console\Command;

class RebuildDeviceLatestStatus extends Command
{
    protected $signature = 'devices:rebuild-latest-status
                            {--chunk=1000 : Number of current rows processed per batch}';

    protected $description = 'Rebuild the current device status projection from the latest valid reading of each sensor';

    public function handle(DeviceLatestStatusService $latestStatus): int
    {
        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize < 1 || $chunkSize > 10000) {
            $this->error('The --chunk option must be between 1 and 10000.');

            return self::FAILURE;
        }

        // The subquery performs the historical winner selection in SQL.  The
        // outer keyset traversal keeps memory bounded to one chunk even with
        // millions of historical readings.
        $latestIds = Reading::latestIdsPerSensor();
        $processed = 0;

        Reading::query()
            ->whereNotNull('sensor_device_id')
            ->where('sensor_device_id', '!=', '')
            ->whereIn('id', $latestIds)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($readings) use ($latestStatus, &$processed) {
                foreach ($readings as $reading) {
                    $latestStatus->upsertFromReading($reading);
                    $processed++;
                }

                $this->output->write('.');
            });

        $this->newLine();
        $this->info("Rebuilt latest status for {$processed} sensor(s).");

        return self::SUCCESS;
    }
}
