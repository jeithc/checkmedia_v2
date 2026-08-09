<?php

use App\Livewire\AuditForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        // Si el usuario no tiene acceso al panel de administración, redirigir a auditoría
        if (! $user->hasAccess('platform.index')) {
            return redirect()->route('audit.form');
        }

        // Otherwise, redirect to dashboard
        return redirect()->route('platform.main');
    }

    return redirect()->route('platform.login');
});

// Política de privacidad pública (requerida por Google Play).
Route::view('/privacidad', 'legal.privacy')->name('legal.privacy');

use App\Http\Controllers\Auth\LoginController;

Route::group(['middleware' => 'web'], function () {
    Route::get('/login', function () {
        return view('vendor.platform.auth.login');
    })->name('platform.login');

    Route::post('/login', [LoginController::class, 'authenticate'])
        ->middleware('throttle:5,1')
        ->name('platform.login.auth');

    Route::post('/logout', function () {
        Illuminate\Support\Facades\Auth::logout();

        return redirect()->route('platform.login');
    })->name('platform.logout');

    Route::get('/cambiar-clave', [\App\Http\Controllers\Auth\ForcePasswordChangeController::class, 'create'])
        ->middleware('auth')
        ->name('password.forced');

    Route::post('/cambiar-clave', [\App\Http\Controllers\Auth\ForcePasswordChangeController::class, 'store'])
        ->middleware(['auth', 'throttle:10,1'])
        ->name('password.forced.update');

    Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'create'])
        ->middleware('guest')
        ->name('password.request');

    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])
        ->middleware(['guest', 'throttle:5,1'])
        ->name('password.email');

    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\NewPasswordController::class, 'create'])
        ->middleware('guest')
        ->name('password.reset');

    Route::post('/reset-password', [\App\Http\Controllers\Auth\NewPasswordController::class, 'store'])
        ->middleware('guest')
        ->name('password.update');
});

Route::get('/audit', AuditForm::class)->name('audit.form');
