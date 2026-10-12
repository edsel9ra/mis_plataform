<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $previousLocale = app()->getLocale();

        // The explicit app header prevents ambient browser/test headers from
        // overriding the locale selected in the MIS interface.
        $localeHeader = (string) $request->header('X-MIS-Locale', '');
        $locale = trim(explode(',', $localeHeader)[0] ?? '');
        $locale = strtolower(trim(explode(';', $locale)[0] ?? ''));
        $locale = explode('-', $locale)[0] ?? '';

        if (! in_array($locale, config('app.available_locales'), true)) {
            $locale = config('app.fallback_locale', 'es');
        }

        // Keep app()->getLocale() and the translator in sync for services that
        // localize structured values such as plan names and recommendations.
        app()->setLocale($locale);

        try {
            return $next($request);
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
