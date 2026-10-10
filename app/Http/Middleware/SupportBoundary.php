<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class SupportBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($request->route('locale'));
        try {
            $response = $next($request);
        } catch (\Throwable $error) {
            $response = response()->view('commerce.unavailable', [], $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503);
        }
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
