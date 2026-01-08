<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\AuditForm;

Route::get('/', function () {
    return redirect()->route('platform.login');
});

use App\Http\Controllers\Auth\LoginController;

Route::group(['middleware' => 'web'], function () {
    Route::get('/login', function () {
        return view('vendor.platform.auth.login');
    })->name('platform.login');

    Route::post('/login', [LoginController::class, 'authenticate'])
        ->name('platform.login.auth');

    Route::post('/logout', function () {
        Auth::logout();
        return redirect()->route('platform.login');
    })->name('platform.logout');
});

Route::get('/audit', AuditForm::class)->name('audit.form');
