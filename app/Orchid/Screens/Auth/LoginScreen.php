<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Password;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LoginScreen extends Screen
{
    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $password;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'remember' => true,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return __('Login');
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return __('Enter your username and password to log in.');
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('username')
                    ->title(__('Username'))
                    ->placeholder(__('Enter your username'))
                    ->required()
                    ->autofocus(),

                Password::make('password')
                    ->title(__('Password'))
                    ->placeholder(__('Enter your password'))
                    ->required(),

                Button::make(__('Login'))
                    ->icon('bs.box-arrow-in-right')
                    ->method('authenticate')
                    ->type(\Orchid\Support\Color::PRIMARY),
            ])->title(__('Authentication')),
        ];
    }

    /**
     * Handle the authentication attempt.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('username', 'password');
        $remember = $request->filled('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            Toast::info(__('Welcome back!'));

            return redirect()->intended(route(config('platform.index')));
        }

        return back()->withErrors([
            'username' => __('The provided credentials do not match our records.'),
        ])->onlyInput('username');
    }
}
