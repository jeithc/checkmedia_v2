<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Orchid\Support\Facades\Toast;

class LoginController extends Controller
{
    /**
     * Handle an authentication attempt.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            // A deactivated account must not get a session. The UI promises
            // that unchecking "Is Active" prevents logging in.
            if (! Auth::user()->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'username' => __('Esta cuenta está desactivada.'),
                ])->onlyInput('username');
            }

            $request->session()->regenerate();

            Toast::info(__('Bienvenido de nuevo!'));

            $user = Auth::user();

            // Si el usuario no tiene acceso al panel de administración, redirigir a auditoría
            if (! $user->hasAccess('platform.index')) {
                return redirect()->route('audit.form');
            }

            return redirect()->intended(route(config('platform.index')));
        }

        return back()->withErrors([
            'username' => __('Las credenciales proporcionadas no coinciden con nuestros registros.'),
        ])->onlyInput('username');
    }
}
