<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('regions', 'uid')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->integer('uid')->nullable()->after('uuid');
            });
        }

        if (! Schema::hasColumn('warehouses', 'region_uid')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->integer('region_uid')->nullable()->after('region_uuid');
            });
        }

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['region_uuid']);
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->renameColumn('uuid', 'frs_id');
            $table->renameColumn('uid', 'nms_id');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->renameColumn('region_uuid', 'region_frs_id');
            $table->renameColumn('region_uid', 'region_nms_id');
            $table->foreign('region_frs_id')
                ->references('frs_id')
                ->on('regions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['region_frs_id']);
            $table->renameColumn('region_frs_id', 'region_uuid');
            $table->renameColumn('region_nms_id', 'region_uid');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->renameColumn('frs_id', 'uuid');
            $table->renameColumn('nms_id', 'uid');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreign('region_uuid')
                ->references('uuid')
                ->on('regions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }
};
