<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Models\ReadingStat;
use Illuminate\Console\Command;

class RefreshReadingStats extends Command
{
    protected $signature = 'readings:refresh-stats';

    protected $description = 'Recount readings into reading_stats, so the readings page never counts the big table';

    public function handle(): int
    {
        // Only level and deleted_at are read, so a covering index can answer this.
        $byLevel = Reading::query()
            ->toBase()
            ->selectRaw('level, COUNT(*) AS total')
            ->groupBy('level')
            ->get();

        $counts = ['total' => 0, 'normal' => 0, 'severe' => 0, 'critical' => 0];

        foreach ($byLevel as $row) {
            $level = strtolower(trim((string) $row->level)) ?: 'normal';
            $counts['total'] += (int) $row->total;

            if ($level !== 'total' && isset($counts[$level])) {
                $counts[$level] += (int) $row->total;
            }
        }

        $stat = ReadingStat::query()->firstOrNew(['id' => 1]);
        $stat->id = 1;
        $stat->fill($counts);
        $stat->updated_at = now(); // also moves when the counts did not change
        $stat->save();

        $this->info('Reading stats refreshed: ' . json_encode($counts));

        return self::SUCCESS;
    }
}
