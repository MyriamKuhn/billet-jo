<?php

use App\Http\Controllers\Api\CartController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('cart')->group(function () {

    // This route is used to get the current cart for the authenticated user or guest.
    Route::get('/', [CartController::class, 'show'])
        ->name('cart.show');

    // This route is used to clear the cart for the authenticated user.
    Route::delete('/items', [CartController::class, 'clearCart'])
        ->name('cart.items.clear')
        ->middleware('auth:sanctum');

    // This route is used to update the cart for the authenticated user or guest.
    Route::patch('/items/{product}', [CartController::class, 'updateItem'])
        ->name('cart.items.update');

})->middleware([CheckOriginMiddleware::class]);
