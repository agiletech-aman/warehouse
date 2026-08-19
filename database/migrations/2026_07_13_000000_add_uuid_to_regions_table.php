<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('regions', 'uuid')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        DB::table('regions')
            ->whereNull('uuid')
            ->orderBy('id')
            ->eachById(function ($region) {
                DB::table('regions')
                    ->where('id', $region->id)
                    ->update(['uuid' => (string) Str::uuid7()]);
            });

        Schema::table('regions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        if (! Schema::hasIndex('regions', 'regions_uuid_unique')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
