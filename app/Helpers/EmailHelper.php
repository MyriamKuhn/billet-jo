<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * Utility class for generating and validating email verification tokens.
 */
class EmailHelper
{
    /**
     * Hash a token using HMAC-SHA256 with the application key.
     *
     * @param  string  $token  The raw token to hash.
     * @return string          The resulting HMAC-SHA256 hash.
     */
    public static function hashToken(string $token): string
    {
        $secret = config('app.key');
        return hash_hmac('sha256', $token, $secret);
    }

    /**
     * Verify that a raw token corresponds to a given hash.
     *
     * @param  string  $token  The raw token provided by the user.
     * @param  string  $hash   The stored HMAC-SHA256 hash.
     * @return bool            True if the token matches the hash; false otherwise.
     */
    public static function verifyToken(string $token, string $hash): bool
    {
        $secret     = config('app.key');
        $calculated = hash_hmac('sha256', $token, $secret);
        return hash_equals($hash, $calculated);
    }

    /**
     * Generate a new random token and its corresponding hash.
     *
     * @param  int    $length  Length of the generated raw token (default: 60).
     * @return array{raw: string, hash: string}
     *                         An associative array containing:
     *                         - 'raw': the plaintext token
     *                         - 'hash': the HMAC-SHA256 hash of the token
     */
    public static function makeTokenPair(int $length = 60): array
    {
        $raw  = Str::random($length);
        $hash = self::hashToken($raw);
        return [$raw, $hash];
    }
}

