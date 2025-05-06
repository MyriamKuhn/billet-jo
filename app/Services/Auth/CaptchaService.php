<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Factory as HttpClient;

class CaptchaService
{
    public function __construct(protected string $secret, protected string $verifyUrl, protected HttpClient $http) {}

    /**
     * Verify the captcha token with Google reCAPTCHA.
     *
     * @param string $token
     * @return bool
     */
    public function verify(string $token): bool
    {
        if (empty($this->secret)) {
            Log::error('reCAPTCHA secret key is not configured');
            return false;
        }

        try {
            $response = $this->http
                ->retry(3, 100)
                ->asForm()
                ->post($this->verifyUrl, [
                    'secret'   => $this->secret,
                    'response' => $token,
                ]);

            // If google returns a HTTP error, we log it and return false
            if (!$response->successful()) {
                Log::warning('reCAPTCHA verification invalid', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            if (empty($data['success'])) {
                // Pour reCAPTCHA v3, aussi vérifier 'score' >= threshold
                Log::warning('reCAPTCHA validation failed', $data);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('Error during reCAPTCHA verification', [
                'exception' => $e,
            ]);
            return false;
        }
    }
}
