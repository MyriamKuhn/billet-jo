<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailUpdateNotFoundException extends NotFoundHttpException
{
    protected $message = 'Invalid or expired verification token';
    public function getErrorCode(): string { return 'email_update_not_found'; }
}
