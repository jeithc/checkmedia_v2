<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the Efectimedios branded login', function () {
    $response = $this->get(route('platform.login'));

    $response->assertOk()
        ->assertSee('aria-label="Efectimedios"', false)
        ->assertSee('fill="currentColor"', false)
        ->assertSee('Iniciar sesión')
        ->assertSee('¿Olvidaste tu contraseña?')
        ->assertSee('Recordarme')
        ->assertSee('brand/favicon.svg', false)
        ->assertSee('css/login.css', false)
        ->assertSee('name="username"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="remember"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('action="'.route('platform.login.auth').'"', false)
        ->assertSee('href="'.route('password.request').'"', false)
        ->assertDontSee('#c60813', false)
        ->assertDontSee('logo.png', false)
        ->assertDontSee('logoefectimedios.png', false);
});

it('ships the brand stylesheet with the official red and without legacy reds', function () {
    $css = file_get_contents(public_path('css/login.css'));

    expect($css)->toContain('#dc003d')
        ->not->toContain('#c60813')
        ->not->toContain('#dd0915')
        ->not->toContain('#a30c15');
});

it('logs in an admin with valid credentials', function () {
    $user = User::factory()->create([
        'username' => 'admin-user',
        'password' => bcrypt('secret123'),
        'permissions' => ['platform.index' => true],
    ]);

    $this->post(route('platform.login.auth'), [
        'username' => 'admin-user',
        'password' => 'secret123',
    ])->assertRedirect(route('platform.main'));

    $this->assertAuthenticatedAs($user);
});

it('sends users without panel access to the audit form', function () {
    User::factory()->create([
        'username' => 'field-user',
        'password' => bcrypt('secret123'),
        'permissions' => [],
    ]);

    $this->post(route('platform.login.auth'), [
        'username' => 'field-user',
        'password' => 'secret123',
    ])->assertRedirect(route('audit.form'));

    $this->assertAuthenticated();
});

it('rejects invalid credentials and shows the error on the branded login', function () {
    User::factory()->create(['username' => 'admin-user', 'password' => bcrypt('secret123')]);

    $this->from(route('platform.login'))
        ->post(route('platform.login.auth'), [
            'username' => 'admin-user',
            'password' => 'wrong',
        ])
        ->assertRedirect(route('platform.login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();

    $this->get(route('platform.login'))
        ->assertSee('Las credenciales proporcionadas no coinciden con nuestros registros.')
        ->assertSee('value="admin-user"', false)
        ->assertSee('aria-invalid="true"', false);
});
