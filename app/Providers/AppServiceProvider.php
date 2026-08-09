<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiters();

        \Illuminate\Support\Facades\Gate::before(static function ($user) {
            if (method_exists($user, 'hasAccess') && $user->is_superuser) {
                return true;
            }
        });

        if (env('APP_ENV') === 'production' || env('APP_ENV') === 'prod') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            request()->server->set('HTTPS', 'on');
        }

        // Prevent Livewire/Alpine from re-initializing on Turbo navigation (Orchid uses Turbo for SPA)
        \Livewire\Livewire::useScriptTagAttributes([
            'data-turbo-eval' => 'false',
            'data-navigate-once' => 'true',
        ]);
    }

    /**
     * Login throttling is keyed by username first, not only by IP.
     *
     * Everyone behind the same NAT or reverse proxy shares one IP, so an
     * IP-only bucket would let a handful of failed logins lock out the whole
     * company. The per-IP limit stays as a looser backstop against someone
     * spraying many usernames from one source.
     */
    private function configureRateLimiters(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $username = \Illuminate\Support\Str::lower((string) $request->input('username'));

            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                    ->by('login:'.$username.'|'.$request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(30)
                    ->by('login-ip:'.$request->ip()),
            ];
        });
    }
}
