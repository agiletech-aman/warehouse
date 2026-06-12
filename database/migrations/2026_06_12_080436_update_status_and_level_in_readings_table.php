<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {

            $table->string('status', 20)
                  ->default('online')
                  ->change();

            $table->string('level', 20)
                  ->nullable()
                  ->change();

        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {

            $table->string('status', 20)
                  ->default('normal')
                  ->change();

            $table->string('level', 20)
                  ->nullable()
                  ->change();

        });
    }
};