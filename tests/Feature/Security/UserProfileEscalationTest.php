<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Reaching /admin/profile at all requires platform.index, so the escalation
 * vector is a limited back-office user (not a field auditor, who is gated out
 * of /admin entirely). Such a user must still not be able to promote itself.
 */
function backOfficeUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Back Office',
        'email' => 'back@test.com',
        'username' => 'backoffice',
        'password' => bcrypt('secret123'),
        'permissions' => ['platform.index' => true, 'maintenance.view' => true],
    ], $attributes));
}

function postProfile(User $user, array $userInput)
{
    return test()->actingAs($user)
        ->post(route('platform.profile', ['method' => 'save']), ['user' => $userInput]);
}

/**
 * UserEditLayout hides the is_superuser / is_active / must_change_password
 * checkboxes on the profile route with canSee(), but that only affects
 * rendering — the save() handler must not accept them from the request.
 */
test('a back office user cannot make themselves superuser via their profile', function () {
    $user = backOfficeUser();

    expect($user->is_superuser)->toBeFalsy();

    postProfile($user, [
        'name' => 'Back Office',
        'username' => 'backoffice',
        'email' => 'back@test.com',
        'is_superuser' => true,
    ]);

    expect((bool) $user->fresh()->is_superuser)->toBeFalse();
});

test('a back office user cannot grant themselves permissions via their profile', function () {
    $user = backOfficeUser();

    postProfile($user, [
        'name' => 'Back Office',
        'username' => 'backoffice',
        'email' => 'back@test.com',
        'permissions' => ['platform.systems.users' => true],
    ]);

    expect($user->fresh()->hasAccess('platform.systems.users'))->toBeFalse();
});

test('a deactivated user cannot reactivate themselves via their profile', function () {
    $user = backOfficeUser(['is_active' => false]);

    postProfile($user, [
        'name' => 'Back Office',
        'username' => 'backoffice',
        'email' => 'back@test.com',
        'is_active' => true,
    ]);

    expect((bool) $user->fresh()->is_active)->toBeFalse();
});

test('a user can still update their own name and email', function () {
    $user = backOfficeUser();

    postProfile($user, [
        'name' => 'Nuevo Nombre',
        'username' => 'backoffice',
        'email' => 'nuevo@test.com',
    ]);

    $fresh = $user->fresh();

    expect($fresh->name)->toBe('Nuevo Nombre')
        ->and($fresh->email)->toBe('nuevo@test.com');
});
