<?php

namespace App\Console\Commands;

use App\Models\Reading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteUnknownReadings extends Command
{
    protected $signature = 'readings:delete-unknown 
    {--days=0 : Only delete rows older than N days, 0 = all}
    {--dry-run : Show count without deleting}';

    public function handle(): int
{
    $days = (int) $this->option('days');
    $dryRun = (bool) $this->option('dry-run');
    $chunkSize = 1000;

    $query = Reading::query()->where('level', 'unknown');

    if ($days > 0) {
        $query->where('recorded_at', '<=', now()->subDays($days));
    }

    $totalMatching = (clone $query)->count();

    if ($totalMatching === 0) {
        $this->info('No unknown-level readings found. Nothing to do.');
        return self::SUCCESS;
    }

    if ($dryRun) {
        $this->info("[Dry run] {$totalMatching} unknown-level reading(s) would be deleted.");
        return self::SUCCESS;
    }

    $this->info("Found {$totalMatching} unknown-level reading(s). Deleting in batches of {$chunkSize}...");

    $deletedTotal = 0;

    try {
        do {
            $deletedInBatch = (clone $query)
                ->orderBy('id')
                ->limit($chunkSize)
                ->delete();

            $deletedTotal += $deletedInBatch;

            if ($deletedInBatch > 0) {
                $this->line("Deleted {$deletedInBatch} row(s)... ({$deletedTotal}/{$totalMatching})");
            }
        } while ($deletedInBatch === $chunkSize);
    } catch (\Throwable $e) {
        Log::error('Cron: failed while deleting unknown-level readings', [
            'error' => $e->getMessage(),
            'deleted_before_failure' => $deletedTotal,
        ]);

        $this->error("Deletion failed after removing {$deletedTotal} row(s): {$e->getMessage()}");
        return self::FAILURE;
    }

    $this->info("Done. Deleted {$deletedTotal} unknown-level reading(s).");

    Log::info('Cron: unknown-level readings cleanup completed', [
        'deleted' => $deletedTotal,
        'days_filter' => $days ?: 'all',
    ]);

    return self::SUCCESS;
}
}