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
        $middleware->trustProxies(at: '*');

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
