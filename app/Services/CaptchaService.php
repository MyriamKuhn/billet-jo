<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptchaService
{
    /**
     * Verify the captcha token with Google reCAPTCHA.
     *
     * @param string $token
     * @return bool
     */
    public function verify(string $token): bool
    {
        try {
            $response = Http::withOptions([
                'verify' => app()->environment('production'), // Deactivate SSL verification in local environment
            ])->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => env('RECAPTCHA_SECRET_KEY'),
                'response' => $token,
            ]);

            $data = $response->json();

            // Check if the response is valid and contains success
            if ($data['success'] ?? false) {
                return true;
            }

            Log::warning('Captcha failed', ['data' => $data]);
            return false;

        } catch (\Exception $e) {
            Log::error('Error during captcha verification', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);
            return false;
        }
    }
}
