<?php

namespace App\Http\Middleware;

use App\Services\Admin\Activity;
use Closure;
use Illuminate\Http\Request;

final class StorefrontActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (config('dashboard.enabled') && $request->isMethod('GET') && $response->getStatusCode() === 200
            && $request->routeIs('home', 'catalogue', 'product', 'information', 'cart.show', 'checkout.show')
            && ! preg_match('/bot|crawler|spider/i', (string) $request->userAgent())) {
            try {
                $subject = $request->route()->getName().'|'.$request->route('locale');
                $type = $request->routeIs('product') ? 'product_view' : ($request->routeIs('checkout.show') ? 'checkout_view' : 'page_view');
                if ($request->routeIs('product')) {
                    $subject .= '|'.$request->route('slug');
                }
                // No query strings, IP, visitor/session identifier, referring URL or user-agent are retained.
                app(Activity::class)->aggregate($type, $subject);
            } catch (\Throwable) {
                // Statistics never prevent a customer page from loading.
            }
        }

        return $response;
    }
}
