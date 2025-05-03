<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('products')->group(function () {
    // This route is used to get all products with optional filters and sorting parameters.
    Route::get('/', [ProductController::class, 'index'])
        ->name('product.index');

    // This route is used to create a new product.
    Route::post('/', [ProductController::class, 'store'])
        ->name('product.store')
        ->middleware('auth:sanctum');

    // This route is used to get all products with optional filters and sorting parameters only for the admin.
    Route::get('/all', [ProductController::class, 'getProducts'])
        ->name('product.all')
        ->middleware('auth:sanctum');

    // This route is used to get a specific product by its ID.
    Route::get('/{product}', [ProductController::class, 'show'])
        ->name('product.show');

    // This route is used to update an existing product.
    Route::put('/{product}', [ProductController::class, 'update'])
        ->name('product.update')
        ->middleware('auth:sanctum');
})->middleware([CheckOriginMiddleware::class]);

