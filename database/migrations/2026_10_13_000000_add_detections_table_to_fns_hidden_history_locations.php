<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History API alerts are shown on both FNS pages, so a hidden location belongs to one detections table.
     * Existing rows stay null and are treated as fns_detections.
     */
    public function up(): void
    {
        Schema::table('fns_hidden_history_locations', function (Blueprint $table) {
            $table->string('detections_table')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('fns_hidden_history_locations', function (Blueprint $table) {
            $table->dropColumn('detections_table');
        });
    }
};
