<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminBoundary
{
    public const CSP = "default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self'; object-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        // Check the actual peer, not a forwarded header supplied by a client.
        if (! app()->environment('local', 'testing')
            || ! in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            || ! in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
            $response = response('Administration locale indisponible.', 404);
        } elseif (! $request->routeIs('admin.login', 'admin.authenticate') && ! app(AdminAccess::class)->valid($request)) {
            $response = redirect()->route('admin.login');
        } else {
            $response = $next($request);
        }
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Frame-Options', 'DENY');
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::CSP);
        }

        return $response;
    }
}
