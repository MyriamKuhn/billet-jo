<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CartController;

Route::get('/cart/ping', [CartController::class, 'ping']);
