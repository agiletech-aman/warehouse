<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fns_detections_02', function (Blueprint $table) {
            $table->longText('snapshot_base64')->nullable()->after('snapshot_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fns_detections_02', function (Blueprint $table) {
            $table->dropColumn('snapshot_base64');
        });
    }
};
