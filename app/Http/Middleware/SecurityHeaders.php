<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Header keamanan dasar untuk semua respons web.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    /**
     * Kebijakan konten hanya dipasang pada HTML produksi agar build Vite
     * dan server pengembangan tetap berjalan.
     */
    private const CONTENT_SECURITY_POLICY = [
        "default-src 'self'",
        "script-src 'self' 'nonce-__NONCE__'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->applyNonce();

        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (
            app()->isProduction()
            && $this->isHtml($response)
            && ! $response->headers->has('Content-Security-Policy')
        ) {
            $response->headers->set(
                'Content-Security-Policy',
                str_replace('__NONCE__', (string) $nonce, implode('; ', self::CONTENT_SECURITY_POLICY)),
            );
        }

        return $response;
    }

    /**
     * Nonce dipakai skrip inline (tema awal) dan tag @vite pada produksi.
     */
    private function applyNonce(): ?string
    {
        if (! app()->isProduction()) {
            return null;
        }

        $nonce = base64_encode(random_bytes(16));

        Vite::useCspNonce($nonce);

        return $nonce;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
