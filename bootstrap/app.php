<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trusting '*' meant any client could set X-Forwarded-For and have
        // Laravel believe it, which spoofs the IP behind rate limiting and
        // pollutes logs. The app is deployed on shared hosting with no
        // public-IP load balancer in front, so only loopback/private ranges
        // need to be trusted. Override TRUSTED_PROXIES if a CDN such as
        // Cloudflare is ever put in front (it needs that CDN's ranges).
        $middleware->trustProxies(at: array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))
        ))));

        // Deactivated accounts must not be usable, whether through an existing
        // session or an API token issued before the deactivation.
        // The web group resolves the session guard lazily, so appending here is
        // enough. The API equivalent is applied inside routes/api.php instead,
        // because group middleware runs before the route's auth:sanctum and
        // $request->user() would still be null here.
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);

        // Force rotation of known-weak passwords (must_change_password).
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsurePasswordIsChanged::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
