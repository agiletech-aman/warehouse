<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_latest_status', function (Blueprint $table) {
            // This is a current-state projection, so a sensor identifier is its
            // stable key rather than an auto-incrementing historical row id.
            $table->string('sensor_device_id')->primary();

            $table->string('device_type', 32)->nullable();
            $table->string('device_name')->nullable();
            $table->string('status', 20)->default('online');
            $table->string('level', 20)->nullable();
            $table->decimal('reading_value', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('device_ip')->nullable();
            $table->string('port', 20)->nullable();

            $table->string('region')->nullable();
            $table->string('region_code', 50)->nullable();
            $table->string('warehouse')->nullable();
            $table->string('warehouse_code', 50)->nullable();
            $table->string('godown')->nullable();
            $table->string('compartment')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['device_type', 'status'], 'dls_type_status_idx');
            $table->index(['device_type', 'level'], 'dls_type_level_idx');
            $table->index(['region_code', 'warehouse_code'], 'dls_region_warehouse_idx');
            $table->index('recorded_at', 'dls_recorded_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_latest_status');
    }
};
