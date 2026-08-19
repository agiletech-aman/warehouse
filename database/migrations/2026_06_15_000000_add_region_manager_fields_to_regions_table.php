<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('manager_name', 100)->nullable()->after('region_name');
            $table->string('manager_email', 100)->nullable()->after('manager_name');
            $table->string('manager_phone', 20)->nullable()->after('manager_email');

            $table->index('manager_phone', 'regions_manager_phone_idx');
            $table->index('manager_email', 'regions_manager_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropIndex('regions_manager_phone_idx');
            $table->dropIndex('regions_manager_email_idx');

            $table->dropColumn(['manager_name', 'manager_email', 'manager_phone']);
        });
    }
};

