<?php

namespace App\Http\Middleware;

use App\Services\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(24));
        $request->attributes->set('security.csp_nonce', $nonce);
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // Inline styles remain supported for the existing interface; scripts require self or a per-request nonce.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $request->is('admin', 'admin/*') ? AdminBoundary::CSP
                : "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        }
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        $robots = $request->attributes->get('seo.robots');
        if ($robots !== null) {
            $response->headers->set('X-Robots-Tag', $robots);
        }
        if ($response->getStatusCode() >= 400 || ! app(Seo::class)->onPublicOrigin($request)
            || ($robots === null && ! $request->routeIs('seo.*'))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $admin = $request->is('admin', 'admin/*');
        $commerce = $request->routeIs('cart.*', 'checkout.*', 'order.*', 'returns.*', 'support.*') || $admin;
        if ($request->routeIs('support.*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($admin) {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $response->headers->set('Referrer-Policy', $commerce ? 'no-referrer' : 'strict-origin-when-cross-origin');
        if ($commerce || ($request->hasSession() && $request->session()->has('commerce.cart'))) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
