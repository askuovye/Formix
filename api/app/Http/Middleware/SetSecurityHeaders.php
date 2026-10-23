<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetSecurityHeaders
{
    private const HSTS_MAX_AGE_SECONDS = 31_536_000;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Respostas JSON: nada a carregar nem a embutir. Não sobrescreve uma CSP já
        // definida pela rota (ex.: `sandbox` no download de anexos).
        if (! $headers->has('Content-Security-Policy') && ! str_contains((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE_SECONDS.'; includeSubDomains');
        }

        return $response;
    }
}
