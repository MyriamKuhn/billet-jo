<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Thrown when the requested user cannot be located in the system.
 *
 * Results in a 404 Not Found HTTP response.
 */
class UserNotFoundException extends NotFoundHttpException
{
    /**
     * Default exception message returned to the client.
     *
     * @var string
     */
    protected $message = 'User not found';

    /**
     * Get a machine‑readable error code for API consumers.
     *
     * @return string  A short, snake_case identifier for this error.
     */
    public function getErrorCode(): string
    {
        return 'user_not_found';
    }
}
