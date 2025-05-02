<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('products')->group(function () {
    // This route is used to get all products with optional filters and sorting parameters.
    Route::get('/', [ProductController::class, 'index'])
        ->name('product.index');

    // This route is used to get all products with optional filters and sorting parameters only for the admin.
    Route::get('/all', [ProductController::class, 'getProducts'])
        ->name('product.all')
        ->middleware('auth:sanctum');
})->middleware([CheckOriginMiddleware::class]);

