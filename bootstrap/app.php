<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\AlertNotifier;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsurePasswordChanged::class,
            SecurityHeaders::class,
        ]);

        // Di balik reverse proxy TLS, header X-Forwarded-* harus dipercaya agar
        // skema https dan IP klien terbaca benar (cookie sesi aman, rate limit, log).
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->alias([
            'role' => EnsureRole::class,
            'api.token' => AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Kegagalan di produksi dikirim ke webhook (dibatasi laju per jenis error).
        $exceptions->report(function (Throwable $error): void {
            if (! app()->isProduction()) {
                return;
            }

            AlertNotifier::critical(
                'error:'.md5($error::class.$error->getMessage()),
                'Error produksi: '.$error::class.': '.$error->getMessage(),
                ['url' => request()?->fullUrl(), 'ip' => request()?->ip()],
            );
        });

        // Konteks tambahan pada setiap laporan error (membantu investigasi produksi).
        $exceptions->context(function (): array {
            return [
                'user_id' => auth()->id(),
                'url' => request()?->fullUrl(),
                'ip' => request()?->ip(),
            ];
        });

        // Halaman error memakai layout aplikasi, bukan halaman bawaan Laravel.
        $exceptions->respond(function (Response $response, Throwable $error, Request $request): Response {
            $status = $response->getStatusCode();

            $isHandled = in_array($status, [403, 404, 419, 429, 500, 503], true)
                && ! $request->is('api/*')
                && ! $request->expectsJson()
                && ! $request->header('X-Inertia')
                && ! ($status === 500 && config('app.debug'));

            if (! $isHandled) {
                return $response;
            }

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
