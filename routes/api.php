<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReadingController;

Route::post('/readings', [ReadingController::class, 'store']);