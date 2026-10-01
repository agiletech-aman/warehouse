<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(['warehouse_code'], 'fns_detections_02_warehouse_code_index');
        $this->addIndexIfMissing(['warehouse_code', 'detected_at'], 'fns_detections_02_warehouse_detected_at_index');
    }

    public function down(): void
    {
        foreach ([
            'fns_detections_02_warehouse_code_index',
            'fns_detections_02_warehouse_detected_at_index',
        ] as $index) {
            if (Schema::hasTable('fns_detections_02') && Schema::hasIndex('fns_detections_02', $index)) {
                Schema::table('fns_detections_02', fn (Blueprint $table) => $table->dropIndex($index));
            }
        }
    }

    /** @param array<int, string> $columns */
    private function addIndexIfMissing(array $columns, string $index): void
    {
        if (! Schema::hasTable('fns_detections_02') || Schema::hasIndex('fns_detections_02', $index)) {
            return;
        }

        Schema::table('fns_detections_02', fn (Blueprint $table) => $table->index($columns, $index));
    }
};
