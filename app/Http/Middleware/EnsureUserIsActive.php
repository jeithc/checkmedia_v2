<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the `is_active` flag on every authenticated request.
 *
 * Rejecting at login is not enough on its own: a user deactivated while already
 * signed in would keep a valid session, and an API token issued before the
 * deactivation would keep working until it is revoked. This closes both.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Only an explicit false deactivates. A model instance that simply has
        // not loaded the column (e.g. straight after create(), where DB defaults
        // are not hydrated) must never lock somebody out.
        $attributes = $user->getAttributes();

        if (! array_key_exists('is_active', $attributes) || $user->is_active) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            // Revoke the token being used so the client cannot keep retrying.
            $user->currentAccessToken()?->delete();

            abort(403, 'Esta cuenta está desactivada.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')
            ->withErrors(['username' => 'Esta cuenta está desactivada.']);
    }
}
