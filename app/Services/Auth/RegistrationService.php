<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use App\Services\Auth\CaptchaService;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Handles user registration logic.
 */
class RegistrationService
{
    public function __construct(private CaptchaService $captchaService) {}

    /**
     * Register a new user.
     *
     * @param  array  $data   The registration data
     * @return User           The newly created user
     * @throws HttpResponseException  On validation or captcha failure
     */
    public function register(array $data): User
    {
        // If in production, validate the captcha token
        if (app()->environment('production') && ! $this->captchaService->verify($data['captcha_token'])) {
            Log::warning('Captcha verification failed', [
                'token' => $data['captcha_token'],
            ]);
            throw new HttpResponseException(response()->json([
                'message' => 'Captcha verification failed',
                'code'    => 'captcha_failed',
            ], 422));
        }

        // Ensure the user accepted the terms and conditions
        if ($data['accept_terms'] !== true) {
            throw new HttpResponseException(response()->json([
                'message' => 'You must accept the terms and conditions',
                'code'    => 'terms_not_accepted',
            ], 422));
        }

        // Create the user record
        $user = User::create([
            'firstname'    => $data['firstname'],
            'lastname'     => $data['lastname'],
            'email'        => $data['email'],
            'password_hash'=> Hash::make($data['password']),
            'role'         => 'user',
        ]);

        // Fire the Registered event to send verification email, etc.
        event(new Registered($user));

        return $user;
    }
}
