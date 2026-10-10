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
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $robots = $request->attributes->get('seo.robots');
        if ($robots !== null) {
            $response->headers->set('X-Robots-Tag', $robots);
        }
        if (! app(Seo::class)->onPublicOrigin($request) || $response->getStatusCode() >= 400
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
            $response->headers->set('Content-Security-Policy', AdminBoundary::CSP);
        }
        $response->headers->set('Referrer-Policy', $commerce ? 'no-referrer' : 'strict-origin-when-cross-origin');
        if ($commerce || ($request->hasSession() && $request->session()->has('commerce.cart'))) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
