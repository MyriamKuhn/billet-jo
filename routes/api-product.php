<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('products')->group(function () {

    // This route is used to get all products with optional filters and sorting parameters.
    Route::get('/', [ProductController::class, 'index'])
        ->name('products.index');

    // This route is used to create a new product only for admin.
    Route::post('/', [ProductController::class, 'store'])
        ->name('products.store')
        ->middleware('auth:sanctum');

    // This route is used to get all products with optional filters and sorting parameters only for the admin.
    Route::get('/all', [ProductController::class, 'getProducts'])
        ->name('products.all')
        ->middleware('auth:sanctum');

    // This route is used to get a specific product by its ID.
    Route::get('/{product}', [ProductController::class, 'show'])
        ->name('products.show');

    // This route is used to update an existing product only for admin.
    Route::put('/{product}', [ProductController::class, 'update'])
        ->name('products.update')
        ->middleware('auth:sanctum');

    // This route is used to update the stock quantity, the sale, and the price of a product only for admin.
    Route::put('/{product}/pricing', [ProductController::class, 'updatePricing'])
        ->name('products.update.pricing')
        ->middleware('auth:sanctum');

})->middleware(CheckOriginMiddleware::class);

