<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            ['sensor_device_id', 'deleted_at', 'recorded_at'],
            'readings_sensor_latest_idx'
        );
        $this->addIndexIfMissing(
            ['warehouse_code', 'recorded_at'],
            'readings_wh_code_recorded_idx'
        );
        $this->addIndexIfMissing(
            ['warehouse', 'recorded_at'],
            'readings_wh_name_recorded_idx'
        );
        $this->addIndexIfMissing(['recorded_at'], 'readings_recorded_at_idx');
    }

    public function down(): void
    {
        foreach ([
            'readings_wh_code_recorded_idx',
            'readings_wh_name_recorded_idx',
            'readings_recorded_at_idx',
        ] as $index) {
            if (Schema::hasIndex('readings', $index)) {
                Schema::table('readings', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        // This index may be owned by an earlier latest-reading migration,
        // so do not remove it when rolling this migration back.
    }

    private function addIndexIfMissing(array $columns, string $index): void
    {
        if (Schema::hasIndex('readings', $index)) {
            return;
        }

        Schema::table('readings', function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }
};
