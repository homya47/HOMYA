<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class CommerceBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        if (in_array($locale, config('homya.locales'), true)) {
            app()->setLocale($locale);
        }
        try {
            $response = $next($request);
        } catch (ValidationException $error) {
            $response = $request->expectsJson() ? response()->json(['errors' => $error->errors()], 422)
                : redirect()->back()->withErrors($error->errors())->withInput($request->only(['email', 'name', 'address', 'postal_code', 'city', 'country', 'quantity', 'variant', 'payment_method', 'contract', 'method', 'logistics', 'notes']));
        } catch (HttpExceptionInterface $error) {
            $response = response()->view('commerce.unavailable', [], $error->getStatusCode());
        } catch (Throwable) {
            // Never debug-render SQL/payment exceptions, credentials or customer details.
            $response = response()->view('commerce.unavailable', [], 503);
        }
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($request->routeIs('returns.*', 'order.tracking*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
