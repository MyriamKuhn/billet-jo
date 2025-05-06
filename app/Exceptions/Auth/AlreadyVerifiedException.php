<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AlreadyVerifiedException extends ConflictHttpException
{
    protected $message = 'Email is already verified';

    public function getErrorCode(): string
    {
        return 'already_verified';
    }
}
