<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StorefrontActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(StorefrontActivity::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'body', 'publishable_key', 'secret', 'webhook_secret', 'client_secret', 'refresh_token', 'developer_token', 'access_token']);
        $exceptions->render(function (Throwable $error, Request $request) {
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
