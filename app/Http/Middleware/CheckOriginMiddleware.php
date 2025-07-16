<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to restrict API access to specific allowed origins.
 *
 * Validates the Origin header against a whitelist and denies
 * requests from unauthorized origins with a 403 response.
 */
class CheckOriginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request instance.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next  The next middleware or request handler.
     * @return \Symfony\Component\HttpFoundation\Response  The HTTP response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // List of permitted origins for cross-domain requests
        $allowedOrigin = [
            'http://localhost:3000',
            'http://localhost:8000',
            'https://jo2024.mkcodecreations.dev',
            'https://api-jo2024.mkcodecreations.dev',
        ];

        $origin = $request->headers->get('Origin');

        // If the origin header is missing or not in the whitelist, reject the request
        if (! $origin || ! in_array($origin, $allowedOrigin)) {
            return response()->json(['error' => 'Unauthorized origin'], 403);
        }

        // Continue processing the request
        return $next($request);
    }
}
