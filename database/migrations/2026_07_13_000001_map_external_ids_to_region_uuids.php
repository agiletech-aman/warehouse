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
        $regionIds = [
            'AHMEDABAD' => 1,
            'AMARAVATI' => 18,
            'BANGALORE' => 2,
            'BHOPAL' => 3,
            'BHUBANESWAR' => 13,
            'CHANDIGARH' => 5,
            'CHENNAI' => 7,
            'DELHI' => 8,
            'GUWAHATI' => 9,
            'HYDERABAD' => 10,
            'JAIPUR' => 11,
            'KOCHI' => 12,
            'KOLKATA' => 14,
            'LUCKNOW' => 15,
            'MUMBAI' => 16,
            'PANCHKULA' => 6,
            'PATNA' => 17,
            'RAIPUR' => 4,
        ];

        foreach ($regionIds as $regionName => $externalId) {
            DB::table('regions')
                ->where('region_name', $regionName)
                ->update(['uuid' => (string) $externalId]);
        }

        Schema::table('regions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('regions')
            ->orderBy('id')
            ->eachById(function ($region) {
                DB::table('regions')
                    ->where('id', $region->id)
                    ->update(['uuid' => (string) Str::uuid7()]);
            });

        Schema::table('regions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }
};
