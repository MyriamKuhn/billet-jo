<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Thrown when the email‑update verification token is invalid,
 * expired, or cannot be found in the system.
 *
 * Results in a 404 Not Found HTTP response.
 */
class EmailUpdateNotFoundException extends NotFoundHttpException
{
    /**
     * Default exception message returned to the client.
     *
     * @var string
     */
    protected $message = 'Invalid or expired verification token';

    /**
     * Get a machine‑readable error code for API consumers.
     *
     * @return string  A short, snake_case identifier for this error.
     */
    public function getErrorCode(): string { return 'email_update_not_found'; }
}
