<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('gas', 20)->unique();
            // Inclusive upper bounds: value <= normal_max => normal,
            // value <= severe_max => severe, anything above => critical.
            $table->decimal('normal_max', 12, 4);
            $table->decimal('severe_max', 12, 4);
            $table->timestamps();
        });

        $now = now();

        DB::table('level_thresholds')->insert([
            ['gas' => 'PH3', 'normal_max' => 0.3, 'severe_max' => 0.75, 'created_at' => $now, 'updated_at' => $now],
            ['gas' => 'CO2', 'normal_max' => 800, 'severe_max' => 1700, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('level_thresholds');
    }
};
