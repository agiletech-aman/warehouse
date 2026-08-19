<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
{
    Schema::create('email_routing', function (Blueprint $table) {
        $table->id();
        $table->string('device_type');  // co2, ph3
        $table->string('level');        // normal, severe, critical
        $table->boolean('send_mail')->default(false);
        $table->boolean('send_whatsapp')->default(false);
        $table->timestamps();

        $table->unique(['device_type', 'level']);
    });
}

public function down()
{
    Schema::dropIfExists('email_routing');
}
};
