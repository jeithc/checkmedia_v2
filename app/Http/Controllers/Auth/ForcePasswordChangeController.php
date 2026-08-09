<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Password change available to every authenticated user.
 *
 * It cannot reuse UserProfileScreen::changePassword because that screen lives
 * under /admin and therefore requires platform.index: field auditors could
 * never change their own password, and while must_change_password is set they
 * would be stuck in a redirect loop.
 *
 * EnsurePasswordIsChanged redirects here when a rotation is due, but the screen
 * is reachable at any time so users can change their password voluntarily.
 */
class ForcePasswordChangeController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.force-password-change', [
            'forced' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // Required even in the forced flow: this screen is reachable by any
            // authenticated session, so without it a hijacked session could
            // take over the account and lock the real owner out.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Debes ingresar tu clave actual.',
            'current_password.current_password' => 'La clave actual no es correcta.',
            'password.required' => 'Debes ingresar una clave nueva.',
            'password.confirmed' => 'Las claves no coinciden.',
        ]);

        $user = $request->user();
        $new = $request->input('password');

        // The whole point is to get off a known-weak password, so reusing the
        // current one has to be rejected.
        if (Hash::check($new, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'La clave nueva debe ser distinta de la actual.',
            ]);
        }

        // must_change_password is not mass assignable (it is an access flag).
        $user->forceFill([
            'password' => Hash::make($new),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        // "/" already routes each role to its landing page.
        return redirect()->to('/');
    }
}
