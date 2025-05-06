<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class EmailHelper
{
    /**
     * Hash a token using HMAC-SHA256 with the app key.
     *
     * @param  string  $token  raw token
     * @return string          Hash token
     */
    public static function hashToken(string $token): string
    {
        $secret = config('app.key');
        return hash_hmac('sha256', $token, $secret);
    }

    /**
     * Verify that a raw token matches a stored hash.
     *
     * @param  string  $token raw token
     * @param  string  $hash  Stocked
     * @return bool
     */
    public static function verifyToken(string $token, string $hash): bool
    {
        $secret     = config('app.key');
        $calculated = hash_hmac('sha256', $token, $secret);
        return hash_equals($hash, $calculated);
    }

    /**
     * Generate both a raw token and its hash.
     *
     * @param  int    $length  Lenght of the raw token
     * @return array<string>   [0 => raw, 1 => hash]
     */
    public static function makeTokenPair(int $length = 60): array
    {
        $raw  = Str::random($length);
        $hash = self::hashToken($raw);
        return [$raw, $hash];
    }
}

