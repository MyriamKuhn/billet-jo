<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('payments')->group(function () {

    // This route is used to get all paiements with optional filters and sorting parameters.
    Route::get('/', [PaymentController::class, 'index'])
        ->name('payments.index')
        ->middleware('auth:sanctum');

    // This route is used to initiate a payment.
    Route::post('/', [PaymentController::class, 'store'])
        ->name('payments.store')
        ->middleware('auth:sanctum');

    // This route is used to get the payment status.
    Route::get('/{uuid}', [PaymentController::class, 'showStatus'])
        ->name('payments.showStatus')
        ->middleware('auth:sanctum');

    // This route is used to refund a payment by the admin.
    Route::post('/{uuid}/refund', [PaymentController::class, 'refund'])
        ->name('payments.refund')
        ->middleware('auth:sanctum');

})->middleware(CheckOriginMiddleware::class);


// This route is used for the webhook from stripe.
Route::post('/payments/webhook', [PaymentController::class, 'webhook'])
    ->name('payments.webhook');


Route::prefix('invoices')->group(function () {

    // This route is used to get the list of invoices for a user.
    Route::get('/', [InvoiceController::class, 'index'])
        ->name('invoices.index')
        ->middleware('auth:sanctum');

    // This route is used to get the invoice link for a payment only for the concerned user.
    Route::get('/{filename}', [InvoiceController::class, 'download'])
        ->name('invoices.download')
        ->middleware('auth:sanctum');

    // This route is used to get the invoice link for a payment for a admin.
    Route::get('/admin/{filename}', [InvoiceController::class, 'adminDownload'])
        ->name('invoices.admin.download')
        ->middleware('auth:sanctum');

})->middleware(CheckOriginMiddleware::class);
