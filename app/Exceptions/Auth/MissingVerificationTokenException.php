<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MissingVerificationTokenException extends BadRequestHttpException
{
    protected $message = 'Verification token is missing';
    public function getErrorCode(): string { return 'verification_token_missing'; }
}
