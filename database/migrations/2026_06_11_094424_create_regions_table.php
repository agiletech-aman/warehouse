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
        Schema::create('regions', function (Blueprint $table) {

            $table->id();

            // Unique Region Code
            $table->string('region_code', 20)->unique();

            // Region Name
            $table->string('region_name', 100);



            // Status
            $table->enum('status', ['active', 'inactive'])
                  ->default('active');

            // Soft Delete
            $table->softDeletes();

            // Created At & Updated At
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};