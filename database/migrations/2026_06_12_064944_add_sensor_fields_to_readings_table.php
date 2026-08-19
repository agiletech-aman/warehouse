<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {

            $table->string('sensor_device_id')->nullable()->after('device_id');
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable();
            $table->string('device_ip')->nullable();

            $table->string('region')->nullable();
            $table->string('region_code')->nullable();

            $table->string('warehouse')->nullable();
            $table->string('warehouse_code')->nullable();

            $table->string('godown')->nullable();
            $table->string('compartment')->nullable();

            $table->string('level')->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {

            $table->dropColumn([
                'sensor_device_id',
                'device_name',
                'device_type',
                'device_ip',
                'region',
                'region_code',
                'warehouse',
                'warehouse_code',
                'godown',
                'compartment',
                'level'
            ]);

        });
    }
};