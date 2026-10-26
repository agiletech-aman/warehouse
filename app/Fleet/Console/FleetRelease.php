<?php

namespace App\Fleet\Console;

use App\Fleet\Models\Release;
use App\Fleet\Support\FleetCore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FleetRelease extends Command
{
    protected $signature = 'fleet:release {version} {file} {--signature=} {--notes=} {--latest}';
    protected $description = 'Publish a GasMonitor binary as a fleet release (use for files too big for the web upload)';

    public function handle(): int
    {
        $v = $this->argument('version');
        $f = $this->argument('file');
        if (!is_file($f)) { $this->error('file not found'); return 1; }
        $sha = hash_file('sha256', $f);
        $sig = $this->option('signature');
        $pk = (string)config('fleet.public_key');
        if ($pk !== '' && (!$sig || !FleetCore::verifySignature($pk, $v, $sha, $sig))) {
            $this->error('signature missing/invalid for FLEET_PUBLIC_KEY'); return 1;
        }
        $path = 'fleet/releases/' . $v . '-' . substr($sha, 0, 12);
        Storage::disk(config('fleet.disk'))->put($path, fopen($f, 'rb'));
        $rel = Release::create(['version' => $v, 'file_path' => $path, 'sha256' => $sha, 'signature' => $sig,
            'size' => filesize($f), 'notes' => $this->option('notes')]);
        if ($this->option('latest')) {
            Release::where('id', '!=', $rel->id)->update(['is_latest' => false]);
            $rel->update(['is_latest' => true]);
        }
        $this->info("released $v sha256=$sha");
        return 0;
    }
}
