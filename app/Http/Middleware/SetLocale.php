<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'pl'];
        $cookieName = 'trumonit_locale';

        $routeLocale = $request->route('locale');
        if (is_string($routeLocale) && in_array($routeLocale, $supported, true)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $routeLocale);
            }
            Cookie::queue(cookie()->forever($cookieName, $routeLocale));
            app()->setLocale($routeLocale);

            return $next($request);
        }

        $queryLocale = (string) $request->query('lang', '');
        if ($queryLocale !== '' && in_array($queryLocale, $supported, true)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $queryLocale);
            }
            Cookie::queue(cookie()->forever($cookieName, $queryLocale));
            app()->setLocale($queryLocale);

            return $next($request);
        }

        $sessionLocale = (string) ($request->hasSession() ? $request->session()->get('locale', '') : '');
        if ($sessionLocale !== '' && in_array($sessionLocale, $supported, true)) {
            app()->setLocale($sessionLocale);

            return $next($request);
        }

        $cookieLocale = (string) $request->cookie($cookieName, '');
        if ($cookieLocale !== '' && in_array($cookieLocale, $supported, true)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $cookieLocale);
            }
            app()->setLocale($cookieLocale);

            return $next($request);
        }

        $preferred = (string) $request->getPreferredLanguage($supported);
        $fallback = (string) config('app.locale', 'en');
        $locale = in_array($preferred, $supported, true) ? $preferred : $fallback;
        app()->setLocale($locale);

        return $next($request);
    }
}
