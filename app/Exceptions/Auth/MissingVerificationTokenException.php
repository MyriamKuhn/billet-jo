<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Thrown when the request to verify an email or account
 * does not include the required verification token.
 *
 * Results in a 400 Bad Request HTTP response.
 */
class MissingVerificationTokenException extends BadRequestHttpException
{
    /**
     * Default exception message returned to the client.
     *
     * @var string
     */
    protected $message = 'Verification token is missing';

    /**
     * Get a machine‑readable error code for API consumers.
     *
     * @return string  A short, snake_case identifier for this error.
     */
    public function getErrorCode(): string { return 'verification_token_missing'; }
}
