<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocalLanguages
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Take the first part of the locale (e.g., 'fr' from 'fr,fr')
        $locale = explode(',', $request->header('Accept-Language', 'en'))[0];

        // Ensure the locale is valid
        if (!in_array($locale, ['en', 'fr', 'de'])) {
            $locale = 'en'; // Default to 'en' if the locale is invalid
        }

        // Set the locale for Laravel
        app()->setLocale($locale);

        return $next($request);
    }
}
