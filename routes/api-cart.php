<?php

use App\Http\Controllers\Api\CartController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('cart')->group(function () {
    // This route is used to get all products in the cart.
    Route::get('/', [CartController::class, 'index'])
        ->name('cart.index');

    // This route is used to add a product to the cart.
    Route::post('/', [CartController::class, 'store'])
        ->name('cart.store')
        ->middleware('auth:sanctum');

    // This route is used to remove a product from the cart.
    Route::delete('/{product}', [CartController::class, 'destroy'])
        ->name('cart.destroy')
        ->middleware('auth:sanctum');
})->middleware([CheckOriginMiddleware::class]);
