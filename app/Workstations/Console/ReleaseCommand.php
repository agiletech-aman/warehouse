<?php

namespace App\Workstations\Console;

use App\Workstations\Models\Release;
use App\Workstations\Support\ReleasePublisher;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ReleaseCommand extends Command
{
    protected $signature = 'workstations:release
        {action : upload | latest | yank | unyank | list}
        {version? : Release version, e.g. 1.2.0}
        {file? : Path to the binary or .tar.gz (upload only)}
        {--signature= : Base64 Ed25519 signature of "gasmonitor|<version>|<sha256>"}
        {--type=binary : binary | package}
        {--post-update= : Post-update action name (package only, from config)}
        {--notes= : Release notes}
        {--latest : Mark as latest after upload}';

    protected $description = 'Manage GasMonitor releases from the command line (use for big files)';

    public function handle(): int
    {
        $action = $this->argument('action');
        if ($action === 'list') {
            $this->table(['ID', 'Version', 'Type', 'Latest', 'Yanked', 'Size', 'Uploaded'], Release::orderByDesc('id')->get()
                ->map(fn($r) => [$r->id, $r->version, $r->type, $r->is_latest ? 'yes' : '', $r->yanked ? 'yes' : '',
                    number_format($r->size / 1048576, 1) . ' MB', $r->created_at]));
            return self::SUCCESS;
        }

        $v = (string)$this->argument('version');
        if ($v === '') {
            $this->error('Version required.');
            return self::FAILURE;
        }

        if ($action === 'upload') {
            try {
                $rel = ReleasePublisher::publish($v, (string)$this->argument('file'), $this->option('signature'),
                    (string)$this->option('type'), $this->option('post-update'), $this->option('notes'), (bool)$this->option('latest'));
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $this->info("Uploaded v{$rel->version} ({$rel->type}) sha256={$rel->sha256}" . ($rel->is_latest ? ' [latest]' : ''));
            return self::SUCCESS;
        }

        $rel = Release::where('version', $v)->first();
        if (!$rel) {
            $this->error("Release $v not found.");
            return self::FAILURE;
        }
        if (!in_array($action, ['latest', 'yank', 'unyank'], true)) {
            $this->error('Unknown action. Use upload | latest | yank | unyank | list');
            return self::FAILURE;
        }
        try {
            match ($action) {
                'latest' => ReleasePublisher::makeLatest($rel),
                'yank' => ReleasePublisher::yank($rel),
                'unyank' => ReleasePublisher::yank($rel, false),
            };
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("v$v: $action done.");
        return self::SUCCESS;
    }
}
