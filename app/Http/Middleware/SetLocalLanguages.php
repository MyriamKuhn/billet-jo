<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to set the application locale based on the Accept-Language header.
 *
 * Extracts the primary language tag (e.g., 'fr' from 'fr,fr;q=0.9') and
 * sets the locale if supported. Defaults to 'en' for unsupported or missing values.
 */
class SetLocalLanguages
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request  The current HTTP request instance.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next  The next middleware or handler.
     * @return \Symfony\Component\HttpFoundation\Response  The HTTP response after locale is set.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the first language from the Accept-Language header or default to 'en'
        $locale = explode(',', $request->header('Accept-Language', 'en'))[0];

        // Validate and default the locale
        // List of supported locales
        if (!in_array($locale, ['en', 'fr', 'de'])) {
            $locale = 'en'; // Default to 'en' if the locale is invalid
        }

        // Apply the locale to the application
        app()->setLocale($locale);

        // Continue processing the request
        return $next($request);
    }
}
