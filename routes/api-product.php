<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('product')->group(function () {

})->middleware([CheckOriginMiddleware::class]);

