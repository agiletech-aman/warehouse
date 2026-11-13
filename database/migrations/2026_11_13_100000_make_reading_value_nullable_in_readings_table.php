<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Unknown/offline readings are stored without a value (shown as N/A).
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->decimal('reading_value', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reverted: existing unknown rows hold NULL values.
    }
};
