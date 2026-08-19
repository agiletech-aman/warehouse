<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouses', 'uid')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->integer('uid')->nullable()->after('uuid');
            });
        }

        Schema::table('warehouses', function (Blueprint $table) {
            $table->renameColumn('uuid', 'frs_id');
            $table->renameColumn('uid', 'nms_id');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->renameColumn('frs_id', 'uuid');
            $table->renameColumn('nms_id', 'uid');
        });
    }
};
