<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Reading;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarkStaleDevicesOffline extends Command
{
    protected $signature = 'devices:mark-stale-offline
                            {--minutes=30 : Minutes without a reading before a device is offline}
                            {--dry-run : Report stale devices without updating them}';

    protected $description = 'Mark devices offline when their latest reading is older than the configured cutoff';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        if ($minutes < 1) {
            $this->error('The --minutes option must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes($minutes);

        $staleReadings = Reading::query()
            ->whereNotNull('sensor_device_id')
            ->where('recorded_at', '<=', $cutoff)
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'offline');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('readings as newer_readings')
                    ->whereNull('newer_readings.deleted_at')
                    ->whereColumn('newer_readings.sensor_device_id', 'readings.sensor_device_id')
                    ->where(function ($newer) {
                        $newer->whereColumn('newer_readings.recorded_at', '>', 'readings.recorded_at')
                            ->orWhere(function ($sameTime) {
                                $sameTime->whereColumn('newer_readings.recorded_at', 'readings.recorded_at')
                                    ->whereColumn('newer_readings.id', '>', 'readings.id');
                            });
                    });
            })
            ->get(['id', 'sensor_device_id', 'device_id']);

        if ($staleReadings->isEmpty()) {
            $this->info("No devices have been without readings for {$minutes} minutes.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("{$staleReadings->count()} device(s) would be marked offline.");
            $this->line('Cutoff: '.$cutoff->toDateTimeString());

            return self::SUCCESS;
        }

        $readingIds = $staleReadings->pluck('id');
        $linkedDeviceIds = $staleReadings->pluck('device_id')->filter()->unique()->values();
        $sensorDeviceIds = $staleReadings->pluck('sensor_device_id')->filter()->unique()->values();

        [$updatedReadings, $updatedDevices] = DB::transaction(function () use ($readingIds, $linkedDeviceIds, $sensorDeviceIds) {
            $updatedReadings = Reading::query()
                ->whereKey($readingIds)
                ->update(['status' => 'offline']);

            $updatedDevices = Device::query()
                ->where(function (Builder $query) use ($linkedDeviceIds, $sensorDeviceIds) {
                    if ($linkedDeviceIds->isNotEmpty()) {
                        $query->whereIn('id', $linkedDeviceIds);
                    }

                    if ($sensorDeviceIds->isNotEmpty()) {
                        $method = $linkedDeviceIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('device_code', $sensorDeviceIds);
                    }
                })
                ->where('status', '!=', 'offline')
                ->update(['status' => 'offline']);

            return [$updatedReadings, $updatedDevices];
        });

        $this->info("Marked {$updatedReadings} stale reading(s) offline.");
        $this->line("Synced {$updatedDevices} linked device record(s).");

        return self::SUCCESS;
    }
}
