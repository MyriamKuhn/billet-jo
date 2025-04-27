<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('auth')->group(function () {
    // This route is used to register a new user
    Route::post('/register', [AuthController::class, 'register'])
        ->name('register')
        ->middleware(['throttle:5,1']);

    // This route is used to login a user
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login')
        ->middleware(['throttle:5,1']);

    // This route is used to logout a user
    Route::post('/logout', [AuthController::class,'logout'])
        ->middleware('auth:sanctum')
        ->name('logout');

    // This route is used to enable the two-factor authentication for the user
    Route::post('/enable2FA', [AuthController::class, 'enableTwoFactor'])
        ->middleware('auth:sanctum')
        ->name('enable2FA');

    // This route is used to verify the email address of the user
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verifyEMail'])
        ->middleware(['signed'])
        ->name('verification.verify');

    // This route is used to resend the verification email to the user
    Route::post('/email/resend-verification', [VerificationController::class, 'resendVerificationEmail'])
        ->middleware(['auth:sanctum'])
        ->name('verification.resend');
})->middleware([CheckOriginMiddleware::class]);

