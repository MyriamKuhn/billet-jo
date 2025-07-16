<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Factory as HttpClient;

/**
 * CaptchaService handles the verification of Google reCAPTCHA tokens.
 * It uses the provided secret key and verify URL to validate the token.
 */
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
        // Ensure the secret key is set
        if (empty($this->secret)) {
            Log::error('reCAPTCHA secret key is not configured');
            return false;
        }

        try {
            // Send the token to Google's verification endpoint
            $response = $this->http
                ->retry(3, 100)
                ->asForm()
                ->post($this->verifyUrl, [
                    'secret'   => $this->secret,
                    'response' => $token,
                ]);

            // If Google returns an HTTP error, log and treat as failure
            if (!$response->successful()) {
                Log::warning('reCAPTCHA verification invalid', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            // For v3, you may also check 'score' >= your threshold
            if (empty($data['success'])) {
                Log::warning('reCAPTCHA validation failed', $data);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            // Any exception during HTTP call is logged and treated as failure
            Log::error('Error during reCAPTCHA verification', [
                'exception' => $e,
            ]);
            return false;
        }
    }
}
