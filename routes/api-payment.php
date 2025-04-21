<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;

Route::get('/payment/ping', [PaymentController::class, 'ping']);
