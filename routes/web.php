<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\AuditForm;

Route::get('/', function () {
    return redirect()->route('platform.login');
});

Route::get('/audit', AuditForm::class)->name('audit.form');
