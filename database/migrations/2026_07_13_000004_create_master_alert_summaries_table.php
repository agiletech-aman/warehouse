<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_alert_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('machine_id', 100);
            $table->unsignedBigInteger('total_iot_devices')->default(0);
            $table->unsignedBigInteger('online_co2')->default(0);
            $table->unsignedBigInteger('offline_co2')->default(0);
            $table->unsignedBigInteger('online_ph3')->default(0);
            $table->unsignedBigInteger('offline_ph3')->default(0);
            $table->unsignedBigInteger('normal_co2')->default(0);
            $table->unsignedBigInteger('severe_co2')->default(0);
            $table->unsignedBigInteger('critical_co2')->default(0);
            $table->unsignedBigInteger('normal_ph3')->default(0);
            $table->unsignedBigInteger('severe_ph3')->default(0);
            $table->unsignedBigInteger('critical_ph3')->default(0);
            $table->string('shad_name', 150)->nullable();
            $table->string('column_name', 150)->nullable();
            $table->string('location_name', 150)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('pin_code', 20)->nullable();
            $table->timestamp('snapshot_time', precision: 3);
            $table->timestamps();

            $table->index(['machine_id', 'snapshot_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_alert_summaries');
    }
};
