<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckOriginMiddleware;

Route::prefix('auth')->group(function () {

    // This route is used to register a new user
    Route::post('/register', [AuthController::class, 'register'])
        ->name('auth.register')
        ->middleware('throttle:5,1');

    // This route is used to login a user
    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login')
        ->middleware('throttle:5,1');

    // This route is used to logout a user
    Route::post('/logout', [AuthController::class,'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    // This route is used to enable the two-factor authentication for the user
    Route::post('/2fa/enable', [AuthController::class, 'enableTwoFactor'])
        ->middleware('auth:sanctum')
        ->name('auth.2fa.enable');

    // This route is used to confirm the two-factor authentication for the user
    Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor'])
        ->middleware('auth:sanctum')
        ->name('auth.2fa.confirm');

    // This route is used to disable the two-factor authentication for the user
    Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor'])
        ->middleware('auth:sanctum')
        ->name('auth.2fa.disable');

    // This route is used to reset the password of the user while sending the email
    Route::post('/password/forgot', [AuthController::class,'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('auth.password.forgot');

    // This route is used to reset the password of the user with the token from the email
    Route::post('/password/reset', [AuthController::class,'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('auth.password.reset');

    // This route is used to update the password of the user in the admin panel
    Route::patch('/password', [AuthController::class,'updatePassword'])
        ->middleware('auth:sanctum')
        ->name('auth.password');

    // This route is used to update the email of the user in the admin panel
    Route::patch('/email', [AuthController::class,'updateEmail'])
        ->middleware('auth:sanctum')
        ->name('auth.email');

    // This route is used to resend the verification email to the user
    Route::post('/email/resend', [VerificationController::class, 'resend'])
        ->middleware('throttle:5,1')
        ->name('auth.email.resend');

    // This route is used to verify the new email address of the user after changing it
    Route::get('/email/change/verify', [VerificationController::class, 'verifyNew'])
        ->middleware('signed')
        ->name('auth.email.change.verify');

    // This route is used to revoke the email change request
    Route::get('/auth/email/change/cancel/{token}', [VerificationController::class, 'cancelChange'])
        ->middleware('signed')
        ->name('auth.email.change.cancel');

    // This route is used to verify the email address of the user
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('auth.email.verify');

})->middleware(CheckOriginMiddleware::class);



Route::prefix('users')->group(function () {

    // This route is used to get the list of all users
    Route::get('/', [UserController::class, 'index'])
        ->middleware('auth:sanctum')
        ->name('users.all');

    // This route is used to show a user his profile
    Route::get('/me', [UserController::class, 'showSelf'])
        ->middleware('auth:sanctum')
        ->name('users.me');

    // This route is used to update the users lastname and firstname
    Route::patch('/me', [UserController::class, 'updateSelf'])
        ->middleware('auth:sanctum')
        ->name('users.update.self');

    // This route is used to create a new employee by the admin
    Route::post('/employees', [UserController::class, 'storeEmployee'])
        ->middleware('auth:sanctum')
        ->name('users.employees');

    // This route is used to see if the user has changed his email address only for admins
    Route::get('/email/{user}', [UserController::class, 'checkEmailUpdate'])
        ->middleware('auth:sanctum')
        ->name('users.email.update');

    // This route is used to get the information of a user by the admin or employee
    Route::get('/{user}', [UserController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('users.show');

    // This route is used for an admin to change the information of a user
    Route::patch('/{user}', [UserController::class, 'update'])
        ->middleware('auth:sanctum')
        ->name('users.update.admin');

})->middleware(CheckOriginMiddleware::class);
