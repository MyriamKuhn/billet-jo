<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TicketController;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('tickets')->group(function () {

    // This route is used to get the list of all the tickets only for the admin
    Route::get('/', [TicketController::class, 'index'])
        ->middleware('auth:sanctum')
        ->name('tickets.index');

    // This route is used to create a ticket only for the admin
    Route::post('/', [TicketController::class, 'createForUser'])
        ->middleware('auth:sanctum')
        ->name('tickets.createForUser');

    // This route is used to get the list of all the tickets for the user
    Route::get('/user', [TicketController::class, 'userTickets'])
        ->middleware('auth:sanctum')
        ->name('tickets.user');

    // This route is used to get a list of the amount of tickets that are saled per product for the admin
    Route::get('/admin/sales', [TicketController::class, 'salesStats'])
        ->middleware('auth:sanctum')
        ->name('tickets.admin.sales');

    // This route is used to get a ticket for the admin
    Route::get('/admin/{filename}', [TicketController::class, 'downloadAdminTicket'])
        ->middleware('auth:sanctum')
        ->name('tickets.admin.show');

    // This route is used to download any QR code for admin
    Route::get('/admin/qr/{filename}', [TicketController::class, 'downloadAdminQr'])
        ->middleware('auth:sanctum')
        ->name('tickets.admin.qr');

    // This route is used to change the status of the ticket only for the admin
    Route::put('/admin/{id}/status', [TicketController::class, 'updateStatus'])
        ->middleware('auth:sanctum')
        ->name('tickets.admin.updateStatus');

    // This route is used to download QR code for the user
    Route::get('/qr/{filename}', [TicketController::class, 'downloadQr'])
        ->middleware('auth:sanctum')
        ->name('tickets.user.qr');

    // This route is used for the employee to scan the QR code and mark the ticket as used
    Route::post('/scan/{token}', [TicketController::class, 'scanTicket'])
        ->middleware('auth:sanctum')
        ->name('tickets.scan');

    // This route is used to get a ticket for the specific user
    Route::get('/{filename}', [TicketController::class, 'downloadTicket'])
        ->middleware('auth:sanctum')
        ->name('tickets.user.show');

})->middleware(CheckOriginMiddleware::class);
