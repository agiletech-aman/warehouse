<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // New structure for throttling behavior
            $table->string('type', 20)->nullable()->after('alert_type');
            $table->text('message')->nullable()->after('type');
            $table->timestamp('last_email_at')->nullable()->after('message');
            $table->boolean('active')->default(true)->after('last_email_at');

            // Helpful for upsert-like logic
            $table->index(['device_id', 'type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'type', 'active']);
            $table->dropColumn(['active', 'last_email_at', 'message', 'type']);
        });
    }
};

