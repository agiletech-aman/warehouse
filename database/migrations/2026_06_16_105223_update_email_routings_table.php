<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns
        Schema::table('email_routing', function (Blueprint $table) {
            $table->boolean('warehouse_mail')
                ->default(false)
                ->after('level');

            $table->boolean('warehouse_whatsapp')
                ->default(false)
                ->after('warehouse_mail');

            $table->boolean('regional_mail')
                ->default(false)
                ->after('warehouse_whatsapp');

            $table->boolean('regional_whatsapp')
                ->default(false)
                ->after('regional_mail');
        });

        // Copy old values
        DB::table('email_routing')->update([
            'warehouse_mail' => DB::raw('send_mail'),
            'warehouse_whatsapp' => DB::raw('send_whatsapp'),
        ]);

        // Remove old columns
        Schema::table('email_routing', function (Blueprint $table) {
            $table->dropColumn([
                'send_mail',
                'send_whatsapp',
            ]);
        });
    }

    public function down(): void
    {
        // Restore old columns
        Schema::table('email_routing', function (Blueprint $table) {
            $table->boolean('send_mail')
                ->default(false)
                ->after('level');

            $table->boolean('send_whatsapp')
                ->default(false)
                ->after('send_mail');
        });

        // Copy data back
        DB::table('email_routing')->update([
            'send_mail' => DB::raw('warehouse_mail'),
            'send_whatsapp' => DB::raw('warehouse_whatsapp'),
        ]);

        // Drop new columns
        Schema::table('email_routings', function (Blueprint $table) {
            $table->dropColumn([
                'warehouse_mail',
                'warehouse_whatsapp',
                'regional_mail',
                'regional_whatsapp',
            ]);
        });
    }
};