<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcEmail extends Model
{
    protected $fillable = ['email', 'status'];

    protected $casts = [
        'status' => 'boolean',
    ];
}