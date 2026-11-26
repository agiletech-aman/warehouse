<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the Workstation Manager tables. If the tables of the previous module are still in the
 * database they are RENAMED (data kept), not recreated. The legacy prefix below is the only place
 * the old name appears; it is needed to find those tables.
 */
return new class extends Migration {
    private const LEGACY_PREFIX = 'fleet_';
    private const TABLES = ['releases', 'workstations', 'env_entries', 'commands', 'events'];

    public function up(): void
    {
        foreach (self::TABLES as $t) {
            if (!Schema::hasTable("wsm_$t") && Schema::hasTable(self::LEGACY_PREFIX . $t)) {
                Schema::rename(self::LEGACY_PREFIX . $t, "wsm_$t");
            }
        }

        if (!Schema::hasTable('wsm_releases')) {
            Schema::create('wsm_releases', function (Blueprint $t) {
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
        }
        Schema::table('wsm_releases', function (Blueprint $t) {
            if (!Schema::hasColumn('wsm_releases', 'type')) {
                $t->string('type', 16)->default('binary');          // binary | package
            }
            if (!Schema::hasColumn('wsm_releases', 'post_update')) {
                $t->string('post_update', 64)->nullable();          // key of config post_update_actions
            }
        });

        if (!Schema::hasTable('wsm_workstations')) {
            Schema::create('wsm_workstations', function (Blueprint $t) {
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
        }

        if (!Schema::hasTable('wsm_env_entries')) {
            Schema::create('wsm_env_entries', function (Blueprint $t) {
                $t->id();
                $t->string('scope', 16);              // global | warehouse | workstation
                $t->string('scope_ref', 64)->default('');
                $t->string('key', 64);
                $t->text('value');
                $t->timestamps();
                $t->unique(['scope', 'scope_ref', 'key']);
            });
        }

        if (!Schema::hasTable('wsm_commands')) {
            Schema::create('wsm_commands', function (Blueprint $t) {
                $t->id();
                $t->string('workstation_id', 64)->index();
                $t->string('command', 32);
                $t->string('status', 16)->default('pending'); // pending|sent|done|failed
                $t->string('result', 500)->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('wsm_events')) {
            Schema::create('wsm_events', function (Blueprint $t) {
                $t->id();
                $t->string('workstation_id', 64)->index();
                $t->string('type', 32);
                $t->string('version', 32)->nullable();
                $t->string('message', 500)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('wsm_install_tokens')) {
            Schema::create('wsm_install_tokens', function (Blueprint $t) {
                $t->id();
                $t->char('token_hash', 64)->unique();
                $t->string('warehouse_code', 64);
                $t->unsignedInteger('max_uses')->default(1);
                $t->unsignedInteger('uses')->default(0);
                $t->timestamp('expires_at');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wsm_install_tokens');
        Schema::table('wsm_releases', fn(Blueprint $t) => $t->dropColumn(['type', 'post_update']));
        // Give the data back to the previous names instead of dropping it.
        foreach (self::TABLES as $t) {
            if (Schema::hasTable("wsm_$t") && !Schema::hasTable(self::LEGACY_PREFIX . $t)) {
                Schema::rename("wsm_$t", self::LEGACY_PREFIX . $t);
            }
        }
    }
};
