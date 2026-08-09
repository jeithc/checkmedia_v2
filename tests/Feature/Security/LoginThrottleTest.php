<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('');
    $this->app['cache']->flush();
});

test('the web login is rate limited after repeated failures', function () {
    User::create([
        'name' => 'Victim',
        'email' => 'victim@test.com',
        'username' => 'victim',
        'password' => bcrypt('correct-horse'),
    ]);

    // 5 attempts per minute are allowed; the 6th must be rejected outright.
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('platform.login.auth'), [
            'username' => 'victim',
            'password' => 'wrong-guess-'.$i,
        ])->assertStatus(302);
    }

    $this->post(route('platform.login.auth'), [
        'username' => 'victim',
        'password' => 'wrong-guess-final',
    ])->assertStatus(429);
});

test('the password reset request is rate limited', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('password.email'), ['email' => 'someone@test.com'])
            ->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => 'someone@test.com'])
        ->assertStatus(429);
});

test('the debug email endpoint no longer exists', function () {
    $this->get('/test-email')->assertNotFound();
});
