<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseSanctumTokenCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('sanctum.auth_cookie', 'mis_auth_token');
        $token = $request->cookies->get($cookieName);
        $hasAuthCookie = is_string($token) && $token !== '';
        $rawCookieHeader = '; ' . (string) $request->headers->get('cookie', '');

        if (!$request->bearerToken() && $hasAuthCookie) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        try {
            $response = $next($request);
        } catch (AuthenticationException $exception) {
            $response = response()->json([
                'message' => $exception->getMessage() ?: 'Unauthenticated.',
            ], 401);
        }

        if ($response->getStatusCode() === 401 && ($hasAuthCookie || str_contains($rawCookieHeader, '; ' . $cookieName . '='))) {
            $response->headers->setCookie(
                cookie()->forget($cookieName, '/', null),
            );
        }

        return $response;
    }
}
