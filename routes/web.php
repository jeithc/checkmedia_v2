<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\AuditForm;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auditoria', AuditForm::class)->name('audit.form');
