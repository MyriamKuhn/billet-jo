<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOriginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigin = [
            'http://localhost:3000',
            'http://localhost:8000',
            'https://jo2024.mkcodecreations.dev',
            'https://api-jo2024.mkcodecreations.dev',
        ];

        $origin = $request->headers->get('Origin');

        if (!in_array($origin, $allowedOrigin)) {
            return response()->json(['error' => 'Unauthorized origin'], 403);
        }

        return $next($request);
    }
}
