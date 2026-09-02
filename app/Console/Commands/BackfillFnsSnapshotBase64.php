<?php

namespace App\Console\Commands;

use App\Models\FnsDetection;
use App\Models\FnsDetection02;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackfillFnsSnapshotBase64 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fns:backfill-snapshot-base64';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert existing FNS detection snapshot_path images into Base64 data URIs stored in the DB.';

    public function handle(): int
    {
        foreach ([FnsDetection::class, FnsDetection02::class] as $modelClass) {
            $this->backfill($modelClass);
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function backfill(string $modelClass): void
    {
        $this->info("Backfilling {$modelClass}...");

        $converted = 0;
        $skipped = 0;

        $modelClass::query()
            ->whereNull('snapshot_base64')
            ->whereNotNull('snapshot_path')
            ->where('snapshot_path', '!=', '')
            ->chunkById(50, function ($detections) use (&$converted, &$skipped) {
                foreach ($detections as $detection) {
                    $dataUri = $this->readAsDataUri($detection->snapshot_path);

                    if ($dataUri === null) {
                        $skipped++;
                        $this->warn("  Skipped {$detection->id}: could not read \"{$detection->snapshot_path}\".");

                        continue;
                    }

                    $detection->snapshot_base64 = $dataUri;
                    $detection->save();
                    $converted++;
                }
            });

        $this->info("  Converted: {$converted}, Skipped: {$skipped}");
    }

    private function readAsDataUri(string $snapshotPath): ?string
    {
        $snapshotPath = trim($snapshotPath);

        if (Str::startsWith(strtolower($snapshotPath), ['http://', 'https://'])) {
            $response = Http::timeout(15)->get($snapshotPath);

            if (! $response->successful()) {
                return null;
            }

            $contents = $response->body();
        } else {
            $relativePath = ltrim($snapshotPath, '/');

            if (Str::startsWith($relativePath, 'storage/')) {
                $relativePath = Str::after($relativePath, 'storage/');
            }

            if (! Storage::disk('public')->exists($relativePath)) {
                return null;
            }

            $contents = Storage::disk('public')->get($relativePath);
        }

        if ($contents === null || $contents === false || $contents === '') {
            return null;
        }

        $imageInfo = @getimagesizefromstring($contents);
        $mimeType = $imageInfo['mime'] ?? null;

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }
}
