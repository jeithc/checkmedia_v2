<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a user on the password-change screen while must_change_password is set.
 *
 * Without this the flag was decorative: ImportLegacyData created every migrated
 * account with the password '12345678' and must_change_password = true, and
 * nothing ever asked for a new one.
 *
 * API requests are deliberately let through — the mobile client has no forced
 * rotation flow yet, and blocking it here would lock field auditors out. The
 * login response exposes `must_change_password` so the app can implement it.
 */
class EnsurePasswordIsChanged
{
    /**
     * Routes that stay reachable so the user can actually comply, or leave.
     */
    private const ALLOWED = [
        'password.forced',
        'password.forced.update',
        'platform.login',
        'platform.logout',
        'platform.logout.quick',
        'platform.switch.logout',
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->route('password.forced');
    }
}
