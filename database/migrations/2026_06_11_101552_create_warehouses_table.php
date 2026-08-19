<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {

            $table->id();

            // Region Relationship
            $table->foreignId('region_id')
                  ->constrained('regions')
                  ->cascadeOnDelete();

            // Warehouse Details
            $table->string('warehouse_code',20)->unique();
            $table->string('warehouse_name',150);

            // Manager Details
            $table->string('manager_name',100);
            $table->string('manager_email',100)->nullable();
            $table->string('manager_phone',20)->nullable();


            // Address Details
            $table->text('address')->nullable();
            $table->string('city',100)->nullable();
            $table->string('state',100)->nullable();
            $table->string('country',100)->nullable();

            // Coordinates
            $table->decimal('latitude',10,7)->nullable();
            $table->decimal('longitude',10,7)->nullable();

            // Status
            $table->enum('status',['active','inactive'])
                  ->default('active');

            // Soft Delete
            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};