<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inyecta cabeceras de seguridad estándar en todas las respuestas:
 *
 *  - X-Content-Type-Options: nosniff       — bloquea MIME sniffing
 *  - X-Frame-Options: DENY                 — bloquea iframe (clickjacking)
 *  - Referrer-Policy: strict-origin-...    — limita referrer a otros sitios
 *  - Permissions-Policy: interest-cohort=()  — opt-out FLoC tracking
 *  - X-XSS-Protection: 0                   — desactiva el filtro legacy con bugs
 *  - Strict-Transport-Security             — solo si la request viene por HTTPS
 *
 * No hay CSP estricta porque la API devuelve JSON y los docs ya tienen su propia
 * configuración (Scalar). Si queremos endurecer más en el futuro, añadir CSP
 * default-src 'none' selectivamente.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'interest-cohort=()');
        $response->headers->set('X-XSS-Protection', '0');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
