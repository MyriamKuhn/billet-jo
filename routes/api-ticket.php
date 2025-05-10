<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TicketController;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('tickets')->group(function () {

    // This route is used to get the list of all the tickets only for the admin
    Route::get('/', [TicketController::class, 'index'])
        ->middleware('auth:sanctum')
        ->name('tickets.index');

    // This route is used to get the list of all the tickets for the user
    Route::get('/user', [TicketController::class, 'userTickets'])
        ->middleware('auth:sanctum')
        ->name('tickets.user');

})->middleware(CheckOriginMiddleware::class);
