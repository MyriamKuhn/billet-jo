<?php

use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use Illuminate\Auth\Events\Verified;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\CartService;

// This route is used to register a new user
Route::post('/user/register', [UserController::class, 'register'])
    ->name('user.register')
    ->middleware(['throttle:5,1']);


// This route is used to verify the email address of the user
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verifyEMail'])
    ->middleware(['signed'])
    ->name('verification.verify');


// This route is used to resend the verification email to the user
Route::post('/email/resend-verification', [VerificationController::class, 'resendVerificationEmail'])
    ->middleware(['auth:api'])
    ->name('verification.resend');

