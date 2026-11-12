<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering index for the reports chart: the chart groups readings in a date range by time
 * and gas and reads only these columns, so MySQL can answer it from the index alone instead
 * of looking up every reading row in the table.
 */
return new class extends Migration
{
    private const INDEX = 'readings_chart_cover_idx';

    public function up(): void
    {
        if (Schema::hasIndex('readings', self::INDEX)) {
            return;
        }

        Schema::table('readings', function (Blueprint $table) {
            $table->index(['deleted_at', 'recorded_at', 'device_type', 'reading_value', 'unit'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('readings', self::INDEX)) {
            Schema::table('readings', function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
