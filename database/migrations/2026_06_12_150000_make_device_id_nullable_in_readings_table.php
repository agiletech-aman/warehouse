<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            // Drop FK constraint and make column nullable
            // Laravel can only alter columns if DB driver supports it (MySQL: requires doctrine/dbal).
            // Since this project uses standard migrations, Doctrine is assumed to be available via composer.

            $table->dropForeign(['device_id']);
            $table->unsignedBigInteger('device_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable(false)->change();
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
        });
    }
};

