<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Thrown when a user attempts to verify an email address
 * that has already been marked as verified.
 *
 * This results in a 409 Conflict response.
 */
class AlreadyVerifiedException extends ConflictHttpException
{
    /**
     * Default exception message returned to the client.
     *
     * @var string
     */
    protected $message = 'Email is already verified';

    /**
     * Get a machine‑readable error code for API consumers.
     *
     * @return string  A short, snake_case identifier for this error.
     */
    public function getErrorCode(): string
    {
        return 'already_verified';
    }
}
