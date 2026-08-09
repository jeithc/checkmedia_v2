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
 * Standalone forced password rotation.
 *
 * It cannot reuse UserProfileScreen::changePassword because that screen lives
 * under /admin and therefore requires platform.index — field auditors would be
 * stuck in a redirect loop.
 */
class ForcePasswordChangeController extends Controller
{
    public function create(): View
    {
        return view('auth.force-password-change');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
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
