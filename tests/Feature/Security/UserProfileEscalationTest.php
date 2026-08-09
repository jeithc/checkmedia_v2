<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Reaching /admin/profile at all requires platform.index, so the escalation
 * vector is a limited back-office user (not a field auditor, who is gated out
 * of /admin entirely). Such a user must still not be able to promote itself.
 */
function backOfficeUser(array $guarded = []): User
{
    $user = User::create([
        'name' => 'Back Office',
        'email' => 'back@test.com',
        'username' => 'backoffice',
        'password' => bcrypt('secret123'),
        'permissions' => ['platform.index' => true, 'maintenance.view' => true],
    ]);

    // is_active / is_superuser are not mass assignable by design.
    if ($guarded !== []) {
        $user->forceFill($guarded)->save();
    }

    return $user;
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

/**
 * The user-list modal renders UserEditLayout, which has no `permissions` field,
 * so anything arriving under user[permissions] is injected. It must not be
 * mass assigned onto the target user.
 */
test('injected permissions are ignored by the user list modal', function () {
    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.com', 'username' => 'admin',
        'password' => bcrypt('secret123'),
        'permissions' => ['platform.index' => true, 'system.edit_users' => true],
    ]);

    $victim = User::create([
        'name' => 'Victim', 'email' => 'victim@test.com', 'username' => 'victim',
        'password' => bcrypt('secret123'), 'permissions' => [],
    ]);

    // Orchid resolves the `User $user` argument from the query string
    // (prepareForExecuteMethod turns query params into route params), so the
    // target user is addressed with ?user=<id>.
    $url = route('platform.systems.users', ['method' => 'saveUser']).'?user='.$victim->id;

    $this->actingAs($admin)->post($url, [
        'user' => [
            'name' => 'Victim renamed',
            'username' => 'victim',
            'email' => 'victim@test.com',
            'permissions' => ['platform.systems.users' => true],
        ],
    ]);

    $fresh = $victim->fresh();

    // The legitimate edit went through, the injected permission did not.
    expect($fresh->name)->toBe('Victim renamed')
        ->and($fresh->hasAccess('platform.systems.users'))->toBeFalse();
});
