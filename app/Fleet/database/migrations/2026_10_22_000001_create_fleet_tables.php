<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fleet_releases', function (Blueprint $t) {
            $t->id();
            $t->string('version', 32)->unique();
            $t->string('file_path');
            $t->char('sha256', 64);
            $t->string('signature', 128)->nullable();
            $t->unsignedBigInteger('size');
            $t->text('notes')->nullable();
            $t->boolean('is_latest')->default(false);
            $t->boolean('yanked')->default(false);
            $t->timestamps();
        });
        Schema::create('fleet_workstations', function (Blueprint $t) {
            $t->id();
            $t->string('workstation_id', 64)->unique();
            $t->string('label')->nullable();
            $t->string('warehouse_code', 64)->nullable()->index();
            $t->string('reported_warehouse_code', 64)->nullable();
            $t->text('reported_warehouse_codes')->nullable();
            $t->string('status', 16)->default('pending');
            $t->char('token_hash', 64)->nullable();
            $t->boolean('auto_update')->default(true);
            $t->unsignedBigInteger('target_release_id')->nullable();
            $t->string('current_version', 32)->nullable();
            $t->string('hostname', 128)->nullable();
            $t->string('os', 190)->nullable();
            $t->string('ip', 64)->nullable();
            $t->string('last_ip', 64)->nullable();
            $t->boolean('frozen')->default(false);
            $t->boolean('launcher')->default(false);
            $t->unsignedInteger('devices_total')->default(0);
            $t->unsignedInteger('devices_online')->default(0);
            $t->unsignedInteger('alerts')->default(0);
            $t->boolean('polling_running')->default(false);
            $t->boolean('demo_mode')->default(false);
            $t->unsignedBigInteger('uptime_s')->default(0);
            $t->string('env_applied_hash', 80)->nullable();
            $t->string('update_state', 32)->default('idle');
            $t->string('update_message', 500)->nullable();
            $t->text('bad_versions')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
        Schema::create('fleet_env_entries', function (Blueprint $t) {
            $t->id();
            $t->string('scope', 16);              // global | warehouse | workstation
            $t->string('scope_ref', 64)->default('');
            $t->string('key', 64);
            $t->text('value');
            $t->timestamps();
            $t->unique(['scope', 'scope_ref', 'key']);
        });
        Schema::create('fleet_commands', function (Blueprint $t) {
            $t->id();
            $t->string('workstation_id', 64)->index();
            $t->string('command', 32);
            $t->string('status', 16)->default('pending'); // pending|sent|done|failed
            $t->string('result', 500)->nullable();
            $t->timestamps();
        });
        Schema::create('fleet_events', function (Blueprint $t) {
            $t->id();
            $t->string('workstation_id', 64)->index();
            $t->string('type', 32);
            $t->string('version', 32)->nullable();
            $t->string('message', 500)->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['events', 'commands', 'env_entries', 'workstations', 'releases'] as $n) {
            Schema::dropIfExists("fleet_$n");
        }
    }
};
