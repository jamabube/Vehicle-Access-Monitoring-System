<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security response headers to all HTTP responses.
 *
 * Implements defense-in-depth protections against:
 * - Clickjacking (X-Frame-Options)
 * - MIME-sniffing attacks (X-Content-Type-Options)
 * - XSS via inline scripts (Content-Security-Policy)
 * - Referer leakage (Referrer-Policy)
 * - SSL stripping after HTTPS is configured (Strict-Transport-Security)
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking by blocking iframe embedding
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME-sniffing attacks (e.g., treating CSV as HTML)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control what information is sent in the Referer header
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy - Allow only same-origin resources
        // 'unsafe-inline' for styles is needed for Tailwind's utility classes.
        // fonts.bunny.net serves both the @font-face CSS and the font files
        // themselves (see layouts/app.blade.php), so it needs both style-src
        // (for the <link rel="stylesheet">) and font-src (for the woff2/woff
        // files the returned CSS points at).
        $connectSrc = ["'self'"];

        foreach ($this->reverbWebSocketOrigins() as $origin) {
            $connectSrc[] = $origin;
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data:",
            "font-src 'self' https://fonts.bunny.net",
            'connect-src '.implode(' ', $connectSrc),
            "frame-ancestors 'none'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Only set HSTS (HTTP Strict Transport Security) when HTTPS is configured
        // This prevents SSL stripping attacks by forcing browsers to always use HTTPS
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    /**
     * Build the ws:// / wss:// origins the browser needs to reach the Reverb
     * WebSocket server (see resources/js/echo.js), so the dashboard's
     * real-time push isn't silently blocked by connect-src 'self'.
     *
     * Both schemes are allowed for whichever host/port is configured,
     * because the frontend (via VITE_REVERB_SCHEME) tries http-derived
     * "ws" first and falls back, and local dev commonly runs Reverb over
     * plain ws while production would use wss.
     *
     * @return array<int, string>
     */
    protected function reverbWebSocketOrigins(): array
    {
        $host = config('reverb.apps.apps.0.options.host');

        if (! $host) {
            return [];
        }

        $port = config('reverb.apps.apps.0.options.port');
        $hostAndPort = $port ? "{$host}:{$port}" : $host;

        return [
            "ws://{$hostAndPort}",
            "wss://{$hostAndPort}",
        ];
    }
}
