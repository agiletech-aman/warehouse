<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History API alerts cannot be deleted at the source, so deleted locations are hidden here instead.
     */
    public function up(): void
    {
        Schema::create('fns_hidden_history_locations', function (Blueprint $table) {
            $table->id();
            $table->string('camera_ip', 45)->nullable();
            $table->string('camera_name')->nullable();
            $table->string('godown')->nullable();
            $table->string('compartment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fns_hidden_history_locations');
    }
};
