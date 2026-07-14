<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->uuid('region_uuid')->nullable()->after('uuid');
        });

        DB::table('warehouses')
            ->orderBy('id')
            ->eachById(function ($warehouse) {
                $regionUuid = DB::table('regions')
                    ->where('id', $warehouse->region_id)
                    ->value('uuid');

                if ($regionUuid !== null) {
                    DB::table('warehouses')
                        ->where('id', $warehouse->id)
                        ->update(['region_uuid' => $regionUuid]);
                }
            });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreign('region_uuid')
                ->references('uuid')
                ->on('regions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['region_uuid']);
            $table->dropColumn('region_uuid');
        });
    }
};
