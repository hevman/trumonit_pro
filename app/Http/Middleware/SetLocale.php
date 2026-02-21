<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'pl'];

        $routeLocale = $request->route('locale');
        if (is_string($routeLocale) && in_array($routeLocale, $supported, true)) {
            app()->setLocale($routeLocale);

            return $next($request);
        }

        $queryLocale = (string) $request->query('lang', '');
        if ($queryLocale !== '' && in_array($queryLocale, $supported, true)) {
            app()->setLocale($queryLocale);

            return $next($request);
        }

        $preferred = (string) $request->getPreferredLanguage($supported);
        $fallback = (string) config('app.locale', 'en');
        $locale = in_array($preferred, $supported, true) ? $preferred : $fallback;
        app()->setLocale($locale);

        return $next($request);
    }
}
