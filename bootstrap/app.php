<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            'user' => __DIR__.'/../routes/api-user.php',
            'cart' => __DIR__.'/../routes/api-cart.php',
            'payment' => __DIR__.'/../routes/api-payment.php',
            'ticket' => __DIR__.'/../routes/api-ticket.php',
            'product' => __DIR__.'/../routes/api-product.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
