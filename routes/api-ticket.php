<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TicketController;

Route::get('/ticket/ping', [TicketController::class, 'ping']);
