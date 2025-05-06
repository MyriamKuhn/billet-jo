<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserNotFoundException extends NotFoundHttpException
{
    protected $message = 'User not found';

    /**
     * Code d’erreur personnalisé pour la réponse JSON.
     */
    public function getErrorCode(): string
    {
        return 'user_not_found';
    }
}
