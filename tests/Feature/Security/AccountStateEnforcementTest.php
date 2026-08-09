<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function accountUser(array $guarded = []): User
{
    $user = User::create([
        'name' => 'Auditor',
        'email' => 'auditor@test.com',
        'username' => 'auditor',
        'password' => Hash::make('12345678'),
        'permissions' => ['audit.can_audit' => true],
    ]);

    if ($guarded !== []) {
        $user->forceFill($guarded)->save();
    }

    // Hydrate the DB defaults (is_active) that create() leaves unset.
    return $user->refresh();
}

// ---------------------------------------------------------------- is_active

test('a deactivated user cannot log in on the web', function () {
    accountUser(['is_active' => false]);

    $this->post(route('platform.login.auth'), [
        'username' => 'auditor',
        'password' => '12345678',
    ])->assertSessionHasErrors('username');

    expect(auth()->check())->toBeFalse();
});

test('an active user can still log in on the web', function () {
    accountUser();

    $this->post(route('platform.login.auth'), [
        'username' => 'auditor',
        'password' => '12345678',
    ]);

    expect(auth()->check())->toBeTrue();
});

test('a deactivated user cannot log in through the api', function () {
    accountUser(['is_active' => false]);

    $this->postJson('/api/login', [
        'username' => 'auditor',
        'password' => '12345678',
        'device_name' => 'phone',
    ])->assertStatus(422);
});

test('an existing session stops working once the account is deactivated', function () {
    $user = accountUser();

    $this->actingAs($user)->get(route('audit.form'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->actingAs($user)->get(route('audit.form'))->assertRedirect(route('platform.login'));
});

test('an api token stops working once the account is deactivated', function () {
    $user = accountUser();
    $token = $user->createToken('phone')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/me')->assertOk();

    $user->forceFill(['is_active' => false])->save();

    // The container persists across requests within one test and Sanctum's
    // RequestGuard caches the resolved user, so drop it to model a real
    // second request.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/me')->assertStatus(403);
});

// ------------------------------------------------------ must_change_password

test('a user with must_change_password is redirected to the change screen', function () {
    $user = accountUser(['must_change_password' => true]);

    $this->actingAs($user)
        ->get(route('audit.form'))
        ->assertRedirect(route('password.forced'));
});

test('the change screen itself stays reachable', function () {
    $user = accountUser(['must_change_password' => true]);

    $this->actingAs($user)->get(route('password.forced'))->assertOk();
});

test('changing the password clears the flag and releases the user', function () {
    $user = accountUser(['must_change_password' => true]);

    $this->actingAs($user)->post(route('password.forced.update'), [
        'current_password' => '12345678',
        'password' => 'una-clave-larga-nueva',
        'password_confirmation' => 'una-clave-larga-nueva',
    ])->assertRedirect('/');

    $fresh = $user->fresh();

    expect((bool) $fresh->must_change_password)->toBeFalse()
        ->and(Hash::check('una-clave-larga-nueva', $fresh->password))->toBeTrue();

    $this->actingAs($fresh)->get(route('audit.form'))->assertOk();
});

test('reusing the current password is rejected', function () {
    $user = accountUser(['must_change_password' => true]);

    $this->actingAs($user)->post(route('password.forced.update'), [
        'current_password' => '12345678',
        'password' => '12345678',
        'password_confirmation' => '12345678',
    ])->assertSessionHasErrors('password');

    expect((bool) $user->fresh()->must_change_password)->toBeTrue();
});

test('the api is not blocked but reports the flag so the app can prompt', function () {
    accountUser(['must_change_password' => true]);

    $this->postJson('/api/login', [
        'username' => 'auditor',
        'password' => '12345678',
        'device_name' => 'phone',
    ])
        ->assertOk()
        ->assertJsonPath('user.must_change_password', true);
});

/**
 * The screen is reachable by any authenticated session, not just during a
 * forced rotation, so it must prove knowledge of the current password.
 */
test('the password change requires the current password', function () {
    $user = accountUser();

    $this->actingAs($user)->post(route('password.forced.update'), [
        'current_password' => 'no-es-la-actual',
        'password' => 'una-clave-larga-nueva',
        'password_confirmation' => 'una-clave-larga-nueva',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('12345678', $user->fresh()->password))->toBeTrue();
});

test('a field auditor can change their password without panel access', function () {
    $user = accountUser();

    expect($user->hasAccess('platform.index'))->toBeFalse();

    $this->actingAs($user)->get(route('password.forced'))->assertOk();

    $this->actingAs($user)->post(route('password.forced.update'), [
        'current_password' => '12345678',
        'password' => 'clave-de-auditor-nueva',
        'password_confirmation' => 'clave-de-auditor-nueva',
    ])->assertRedirect('/');

    expect(Hash::check('clave-de-auditor-nueva', $user->fresh()->password))->toBeTrue();
});

test('resetting by email clears a pending forced rotation', function () {
    $user = accountUser(['must_change_password' => true]);
    $token = app('auth.password.broker')->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'clave-desde-el-correo',
        'password_confirmation' => 'clave-desde-el-correo',
    ])->assertRedirect(route('platform.login'));

    expect((bool) $user->fresh()->must_change_password)->toBeFalse();
});
