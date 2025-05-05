<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Predis\Connection\ConnectionException as RedisConnectionException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;


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
        $exceptions->render(function (BadRequestHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Bad request',
                    'code' => 'bad_request'
                ], 400);
            }
        });
        $exceptions->render(function (AuthenticationException|UnauthorizedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Authentication required',
                    'code' => 'unauthenticated'
                ], 401);
            }
        });
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action',
                    'code' => 'forbidden'
                ], 403);
            }
        });
        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found',
                    'code' => 'not_found'
                ], 404);
            }
        });
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Method not allowed',
                    'code' => 'method_not_allowed'
                ], 405);
            }
        });
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'CSRF token mismatch',
                    'code' => 'csrf_token_mismatch'
                ], 419);
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
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many requests',
                    'code' => 'too_many_requests'
                ], 429);
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
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unexpected error',
                    'code'    => 'internal_error',
                ], 500);
            }
        });
    })->create();
