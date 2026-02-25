<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\AuditForm;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        // If user is an auditor (has audit permission but not admin access), redirect to audit form
        if ($user->hasAnyAccess(['audit.can_audit']) && !$user->hasAnyAccess(['platform.index'])) {
            return redirect()->route('audit.form');
        }

        // Otherwise, redirect to dashboard
        return redirect()->route('platform.main');
    }
    return redirect()->route('platform.login');
});

use App\Http\Controllers\Auth\LoginController;

Route::group(['middleware' => 'web'], function () {
    Route::get('/login', function () {
        return view('vendor.platform.auth.login');
    })->name('platform.login');

    Route::get('/test-email', function () {
        try {
            // Force debug mode
            config(['mail.mailers.smtp.local_domain' => 'localhost']);

            \Illuminate\Support\Facades\Log::info('Attempting to send email to jeith2@gmail.com');

            Illuminate\Support\Facades\Mail::to('jeith2@gmail.com')->send(new App\Mail\TestEmail());

            \Illuminate\Support\Facades\Log::info('Email sent command executed successfully');

            return 'Correo enviado correctamente a jeith2@gmail.com. Revisa tu carpeta de spam.';
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Mail Error: ' . $e->getMessage());
            return 'Error al enviar el correo: ' . $e->getMessage();
        }
    });

    Route::post('/login', [LoginController::class, 'authenticate'])
        ->name('platform.login.auth');

    Route::post('/logout', function () {
        Illuminate\Support\Facades\Auth::logout();
        return redirect()->route('platform.login');
    })->name('platform.logout');

    Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'create'])
        ->middleware('guest')
        ->name('password.request');

    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])
        ->middleware('guest')
        ->name('password.email');

    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\NewPasswordController::class, 'create'])
        ->middleware('guest')
        ->name('password.reset');

    Route::post('/reset-password', [\App\Http\Controllers\Auth\NewPasswordController::class, 'store'])
        ->middleware('guest')
        ->name('password.update');
});

Route::get('/audit', AuditForm::class)->name('audit.form');
