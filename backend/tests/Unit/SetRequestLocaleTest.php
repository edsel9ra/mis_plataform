<?php

use App\Http\Middleware\SetRequestLocale;
use Illuminate\Http\Request;

it('uses the locale explicitly selected by the interface', function () {
    $request = Request::create('/api/v1/plans', 'GET', [], [], [], [
        'HTTP_X_MIS_LOCALE' => 'pt-BR',
    ]);

    $response = app(SetRequestLocale::class)->handle($request, function () {
        return response()->json([
            'app_locale' => app()->getLocale(),
            'translator_locale' => app('translator')->getLocale(),
        ]);
    });

    expect($response->getData(true))
        ->toMatchArray(['app_locale' => 'pt', 'translator_locale' => 'pt'])
        ->and(app()->getLocale())->toBe(config('app.locale'));
});

it('falls back to the configured locale for unsupported interface locales', function () {
    $request = Request::create('/api/v1/plans', 'GET', [], [], [], [
        'HTTP_X_MIS_LOCALE' => 'de-DE',
    ]);

    $response = app(SetRequestLocale::class)->handle($request, function () {
        return response()->json([
            'app_locale' => app()->getLocale(),
            'translator_locale' => app('translator')->getLocale(),
        ]);
    });

    expect($response->getData(true))
        ->toMatchArray([
            'app_locale' => config('app.fallback_locale'),
            'translator_locale' => config('app.fallback_locale'),
        ])
        ->and(app()->getLocale())->toBe(config('app.locale'));
});
