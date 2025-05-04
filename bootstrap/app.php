<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Predis\Connection\ConnectionException as RedisConnectionException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;

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
        $middleware->append(\App\Http\Middleware\SetLocalLanguages::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found',
                    'code' => 'not_found'
                ], 404);
            }
        });
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found',
                    'code' => 'not_found'
                ], 404);
            }
        });
        $exceptions->render(function (\RuntimeException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unexpected error',
                    'code' => 'internal_error'
                ], 500);
            }
        });
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unexpected error',
                    'code' => 'internal_error'
                ], 500);
            }
        });
        $exceptions->render(function (RedisConnectionException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Service temporarily unavailable',
                    'code' => 'service_unavailable'
                ], 503);
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Authentication required',
                    'code' => 'unauthenticated'
                ], 401);
            }
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action',
                    'code' => 'forbidden'
                ], 403);
            }
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid',
                    'code'    => 'validation_error',
                    'errors' => $e->errors()
                ], 422);
            }
        });
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unexpected error',
                    'code'    => 'internal_error',
                ], 500);
            }
        });
    })->create();
