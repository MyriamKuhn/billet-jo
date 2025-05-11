<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use App\Services\Auth\CaptchaService;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegistrationService
{
    public function __construct(private CaptchaService $captchaService) {}

    /**
     * Register a new user.
     *
     * @param array $data
     * @return User
     * @throws \Exception
     */
    public function register(array $data): User
    {
        // Check the captcha token if the application is in production
        if (app()->environment('production') && ! $this->captchaService->verify($data['captcha_token'])) {
            Log::warning('Captcha verification failed', [
                'token' => $data['captcha_token'],
            ]);
            throw new HttpResponseException(response()->json([
                'message' => 'Captcha verification failed',
                'code'    => 'captcha_failed',
            ], 422));
        }

        // Create the user
        $user = User::create([
            'firstname'    => $data['firstname'],
            'lastname'     => $data['lastname'],
            'email'        => $data['email'],
            'password_hash'=> Hash::make($data['password']),
            'role'         => 'user',
        ]);

        // Start the email verification sending process
        $user->notify(new \App\Notifications\VerifyEmailNotification());

        return $user;
    }
}
