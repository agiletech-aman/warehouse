<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceLatestStatus;
use App\Services\MasterAlertSummaryService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarkStaleDevicesOffline extends Command
{
    protected $signature = 'devices:mark-stale-offline
                            {--minutes=60 : Minutes without a reading before a device is offline}
                            {--dry-run : Report stale devices without updating them}';

    protected $description = 'Mark current device statuses offline when their latest reading is older than the configured cutoff';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        if ($minutes < 1) {
            $this->error('The --minutes option must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes($minutes);
        $staleCount = $this->staleStatuses($cutoff)->count();

        if ($staleCount === 0) {
            $this->info("No devices have been without readings for {$minutes} minutes.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("{$staleCount} device(s) would be marked offline.");
            $this->line('Cutoff: '.$cutoff->toDateTimeString());

            return self::SUCCESS;
        }

        [$updatedStatuses, $updatedDevices] = DB::transaction(function () use ($cutoff) {
            // Sync manually managed device records before changing the
            // projection predicate from non-offline to offline.
            $updatedDevices = Device::query()
                ->whereIn('device_code', $this->staleStatuses($cutoff)->select('sensor_device_id'))
                ->where('status', '!=', 'offline')
                ->update(['status' => 'offline']);

            $updatedStatuses = $this->staleStatuses($cutoff)->update([
                'status' => 'offline',
                'updated_at' => now(),
            ]);

            return [$updatedStatuses, $updatedDevices];
        });

        if ($updatedStatuses > 0) {
            MasterAlertSummaryService::invalidateDashboardCache();
        }

        $this->info("Marked {$updatedStatuses} stale device status(es) offline.");
        $this->line("Synced {$updatedDevices} linked device record(s).");

        return self::SUCCESS;
    }

    private function staleStatuses($cutoff): Builder
    {
        return DeviceLatestStatus::query()
            ->where('recorded_at', '<=', $cutoff)
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'offline');
            });
    }
}
