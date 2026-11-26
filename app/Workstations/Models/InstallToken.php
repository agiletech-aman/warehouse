<?php

namespace App\Workstations\Models;

use Illuminate\Database\Eloquent\Model;

class InstallToken extends Model
{
    protected $table = 'wsm_install_tokens';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];
}
