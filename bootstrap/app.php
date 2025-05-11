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
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Stripe\Exception\ApiErrorException;
use App\Exceptions\TicketAlreadyProcessedException;
use App\Enums\TicketStatus;

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
        $base = config('app.frontend_url') . '/verification-result';
        $exceptions->render(function (TicketAlreadyProcessedException $e, Request $request) {
            if ($request->is('api/*')) {
                $ticket = $e->ticket;

                $timestamp = match($ticket->status) {
                    TicketStatus::Used->value      => $ticket->used_at,
                    TicketStatus::Refunded->value  => $ticket->refunded_at,
                    TicketStatus::Cancelled->value => $ticket->cancelled_at,
                    default                        => null,
                };

                $timestampString = $timestamp?->toIso8601String() ?? 'unknown time';

                return response()->json([
                    'status'    => $ticket->status,
                    'timestamp' => $timestampString,
                    'user'      => [
                        'firstname' => $ticket->user->firstname,
                        'lastname'  => $ticket->user->lastname,
                        'email'     => $ticket->user->email,
                    ],
                    'event'     => [
                        'name'     => $ticket->product->name,
                        'date'     => $ticket->product->product_details['date'] ?? null,
                        'time'     => $ticket->product->product_details['time'] ?? null,
                        'location' => $ticket->product->product_details['location'] ?? null,
                    ],
                    'code'      => 'ticket_already_processed',
                    'message'   => "This ticket was already {$ticket->status->value} on {$timestampString}",
                ], 409);
            }
        });
        $exceptions->render(function (\App\Exceptions\Auth\UserNotFoundException $e, Request $request) use ($base) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message'      => 'User not found',
                    'code'         => 'user_not_found',
                    'redirect_url' => "$base/invalid",
                ], 404);
            }
        });
        $exceptions->render(function (\App\Exceptions\Auth\InvalidVerificationLinkException $e, Request $request) use ($base) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message'      => 'Invalid verification link',
                    'code'         => 'invalid_verification_link',
                    'redirect_url' => "$base/invalid",
                ], 400);
            }
        });
        $exceptions->render(function (\App\Exceptions\Auth\AlreadyVerifiedException $e, Request $request) use ($base) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message'      => 'Email is already verified',
                    'code'         => 'already_verified',
                    'redirect_url' => "$base/already-verified",
                ], 409);
            }
        });
        $exceptions->render(function (\App\Exceptions\Auth\MissingVerificationTokenException $e, Request $request) use ($base) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message'      => 'Invalid or expired verification token',
                    'code'         => 'verification_token_missing',
                    'redirect_url' => "$base/invalid",
                ], 400);
            }
        });
        $exceptions->render(function (\App\Exceptions\Auth\EmailUpdateNotFoundException $e, Request $request) use ($base) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message'      => 'Email request not found',
                    'code'         => 'email_not_found',
                    'redirect_url' => "$base/invalid",
                ], 404);
            }
        });
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
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Database error',
                    'code'    => 'database_error',
                ], 500);
            }
        });
        $exceptions->render(function (HttpResponseException $e, Request $request) {
            if ($request->is('api/*')) {
                return $e->getResponse();
            }
        });
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code'    => 'http_error',
                ], $e->getStatusCode());
            }
        });
        $exceptions->render(function (ApiErrorException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code'    => 'payment_gateway_error',
                ], 502);
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
