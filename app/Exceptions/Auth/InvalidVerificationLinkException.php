<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Thrown when a verification link is malformed, tampered with,
 * or otherwise cannot be processed.
 *
 * Results in a 400 Bad Request HTTP response.
 */
class InvalidVerificationLinkException extends BadRequestHttpException
{
    /**
     * Default exception message returned to the client.
     *
     * @var string
     */
    protected $message = 'Invalid verification link';

    /**
     * Get a machine‑readable error code for API consumers.
     *
     * @return string  A short, snake_case identifier for this error.
     */
    public function getErrorCode(): string
    {
        return 'invalid_verification_link';
    }
}
