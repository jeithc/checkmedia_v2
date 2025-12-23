<?php

use Illuminate\Support\Facades\Route;

/**
 * Redirect root URL to the Orchid Admin Login.
 * Orchid's default login route is 'platform.login'.
 */
Route::get('/', function () {
    return redirect()->route('platform.login');
});
