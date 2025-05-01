<?php

namespace App\Helpers;

class EmailHelper
{
    /**
     * Hash the email verification token using SHA-256.
     *
     * @param string $token
     * @return string
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
