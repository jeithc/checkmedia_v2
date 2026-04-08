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
}
