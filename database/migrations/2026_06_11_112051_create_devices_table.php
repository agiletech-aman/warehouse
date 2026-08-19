<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {

            $table->id();

            // Warehouse Relationship
            $table->foreignId('warehouse_id')
                  ->constrained('warehouses')
                  ->cascadeOnDelete();

            // Device Information
            $table->string('device_code',20)->unique();
            $table->string('device_name',100);

            // Hardware Details
            $table->string('device_type',50)->nullable();     // ESP32, NodeMCU, Raspberry Pi
            $table->string('model_no',50)->nullable();
            $table->string('serial_no',100)->nullable()->unique();

            // Network Information
            $table->string('mac_address',50)->nullable();
            $table->string('ip_address',50)->nullable();

            // Firmware
            $table->string('firmware_version',50)->nullable();

            // Installation Date
            $table->date('installation_date')->nullable();

            // Last Seen Time
            $table->timestamp('last_seen_at')->nullable();

            // Device Status
            $table->enum('status',[
                'online',
                'offline',
                'maintenance'
            ])->default('offline');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};