<?php

use App\Http\Middleware\ProductionGuard;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StorefrontActivity;
use App\Services\ProductionSecurity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);
        // Evaluate HTTPS after Laravel's proxy handling, before any web session or controller.
        $middleware->append(ProductionGuard::class);
        $middleware->append(StorefrontActivity::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'body', 'publishable_key', 'secret', 'webhook_secret', 'client_secret', 'refresh_token', 'developer_token', 'access_token', 'card_number', 'cardholder', 'expiry', 'expiry_month', 'expiry_year', 'cvc', 'cvv', 'iban', 'bic']);
        $exceptions->report(function (Throwable $error) {
            if (app(ProductionSecurity::class)->localRequest(request())
                || (app()->environment('local', 'testing') && app()->runningInConsole() && request()->server('REMOTE_ADDR') === null)) {
                return null;
            }
            // Exception messages, SQL bindings, trace arguments and request data can contain secrets.
            try {
                Log::error('HOMYA_PRODUCTION_EXCEPTION', ['type' => get_class($error), 'source' => basename($error->getFile()), 'line' => $error->getLine()]);
            } catch (Throwable) {
                // A failed logger must never cause raw exception disclosure.
            }

            return false;
        });
        $exceptions->render(function (Throwable $error, Request $request) {
            // Also cover framework exceptions thrown before the guard with an accidental local .env.
            if (! app(ProductionSecurity::class)->localRequest($request)) {
                config(['app.debug' => false]);
            }
            if ($request->is('admin', 'admin/*') && ! ($error instanceof ValidationException)) {
                $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;

                return response()->view('admin.unavailable', ['status' => $status], $status)->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
            }
            if (! $request->routeIs('returns.*') || $error instanceof ValidationException) {
                return null;
            }
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;

            return response()->view('returns.unavailable', [], $status)
                ->header('Cache-Control', 'no-store, private')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
