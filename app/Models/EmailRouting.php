<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailRouting extends Model
{
    protected $table = 'email_routing';

    protected $fillable = [
        'device_type',
        'level',
        'warehouse_mail',
        'warehouse_whatsapp',
        'regional_mail',
        'regional_whatsapp',
    ];
}