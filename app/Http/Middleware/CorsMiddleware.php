<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request and add CORS headers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\JsonResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verify if the request is an OPTIONS request
        // and handle it accordingly
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 200, [
                'Access-Control-Allow-Origin' => $this->getAllowedOrigin($request),
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }

        // If it's not an OPTIONS request, proceed with the request
        $response = $next($request);

        // Add CORS headers to the response
        $response->headers->set('Access-Control-Allow-Origin', $this->getAllowedOrigin($request));
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }

    /**
     * Function to get the allowed origin for CORS.
     *
     * @param Request $request
     * @return string|null
     */
    private function getAllowedOrigin(Request $request): ?string
    {
        $allowedOrigins = [
            'https://jo2024.mkcodecreations.dev',
            'http://localhost:3000',
        ];

        $origin = $request->headers->get('Origin');

        return in_array($origin, $allowedOrigins) ? $origin : null;
    }
}
