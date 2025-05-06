<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvalidVerificationLinkException extends BadRequestHttpException
{
    protected $message = 'Invalid verification link';

    public function getErrorCode(): string
    {
        return 'invalid_verification_link';
    }
}
