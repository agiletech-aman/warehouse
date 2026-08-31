<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fns_detections_02', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('camera_ip', 45)->index();
            $table->string('camera_name')->index();
            $table->string('warehouse_code', 100)->nullable();
            $table->string('godown')->nullable();
            $table->string('compartment')->nullable();
            $table->enum('detection_type', [
                'person',
                'fire',
                'smoke',
                'weapon',
                'intrusion',
            ]);
            $table->double('confidence');
            $table->string('snapshot_path', 500)->nullable();
            $table->string('bounding_box', 100)->nullable();
            $table->timestamp('detected_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fns_detections_02');
    }
};
