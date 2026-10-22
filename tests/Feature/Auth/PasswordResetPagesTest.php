<?php

use App\Models\User;
use App\Notifications\CustomResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

dataset('password pages', [
    'recuperar' => fn () => route('password.request'),
    'restablecer' => fn () => route('password.reset', ['token' => 'dummy-token', 'email' => 'a@b.co']),
]);

it('renders the Efectimedios brand without legacy assets', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('aria-label="Efectimedios"', false)
        ->assertSee('fill="currentColor"', false)
        ->assertSee('css/login.css', false)
        ->assertSee('<link rel="icon" type="image/svg+xml" href="'.asset('brand/favicon.svg').'">', false)
        ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" sizes="32x32">', false)
        ->assertSee('href="'.route('platform.login').'"', false)
        ->assertSee('Volver a iniciar sesión')
        ->assertSee('name="_token"', false)
        ->assertDontSee('#c60813', false)
        ->assertDontSee('#dd0915', false)
        ->assertDontSee('logo.png', false)
        ->assertDontSee('logoefectimedios.png', false)
        ->assertDontSee('<style', false);
})->with('password pages');

it('renders the forgot password form contract', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Recuperar contraseña')
        ->assertSee('Escribe el correo de tu cuenta y te enviaremos un enlace para restablecerla.')
        ->assertSee('Enviar enlace')
        ->assertSee('action="'.route('password.email').'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="email"', false)
        ->assertSee('type="email"', false);
});

it('renders the reset password form contract with token and email', function () {
    $this->get(route('password.reset', ['token' => 'dummy-token', 'email' => 'a@b.co']))
        ->assertOk()
        ->assertSee('Nueva contraseña')
        ->assertSee('Confirmar contraseña')
        ->assertSee('Guardar contraseña')
        ->assertSee('action="'.route('password.update').'"', false)
        ->assertSee('type="hidden" name="token" value="dummy-token"', false)
        ->assertSee('name="email"', false)
        ->assertSee('value="a@b.co"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false);
});

it('sends the reset link and shows the status message', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'auditor@efectimedios.com']);

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'auditor@efectimedios.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status');

    Notification::assertSentTo($user, CustomResetPassword::class);

    $this->get(route('password.request'))
        ->assertSee('role="status"', false)
        ->assertSee('Te hemos enviado por correo el enlace para restablecer tu contraseña.');
});

it('shows validation errors linked to the email field on forgot password', function () {
    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'no-es-correo'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');

    $message = session('errors')->first('email');

    $this->get(route('password.request'))
        ->assertSee('role="alert"', false)
        ->assertSee('id="login-error-email"', false)
        ->assertSee($message)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="login-error-email"', false)
        ->assertSee('value="no-es-correo"', false);
});

it('shows every reset error linked to its field', function () {
    $url = route('password.reset', ['token' => 'dummy-token', 'email' => 'a@b.co']);

    $this->from($url)
        ->post(route('password.update'), ['token' => 'dummy-token', 'email' => '', 'password' => ''])
        ->assertRedirect($url)
        ->assertSessionHasErrors(['email', 'password']);

    $errors = session('errors')->getBag('default');

    $this->get($url)
        ->assertSee('role="alert"', false)
        ->assertSee($errors->first('email'))
        ->assertSee($errors->first('password'))
        ->assertSee('aria-describedby="login-error-email"', false)
        ->assertSee('aria-describedby="password-hint login-error-password"', false);
});

it('resets the password with a valid token and returns to the login', function () {
    $user = User::factory()->create(['email' => 'auditor@efectimedios.com']);
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'auditor@efectimedios.com',
        'password' => 'NuevaClave123',
        'password_confirmation' => 'NuevaClave123',
    ])->assertRedirect(route('platform.login'))
        ->assertSessionHas('status');

    expect(Hash::check('NuevaClave123', $user->fresh()->password))->toBeTrue();

    $this->get(route('platform.login'))
        ->assertSee('role="status"', false)
        ->assertSee('Tu contraseña ha sido restablecida.');
});

it('renders forgot password validation errors in Spanish', function () {
    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => ''])
        ->assertRedirect(route('password.request'));

    $this->get(route('password.request'))
        ->assertSee('El campo correo electrónico es obligatorio.')
        ->assertDontSee('The email field is required.');
});

it('renders reset password rule errors in Spanish', function () {
    $url = route('password.reset', ['token' => 'dummy-token', 'email' => 'a@b.co']);

    $this->from($url)->post(route('password.update'), [
        'token' => 'dummy-token',
        'email' => 'a@b.co',
        'password' => 'corta',
        'password_confirmation' => 'otra',
    ])->assertRedirect($url);

    $this->get($url)
        ->assertSee('La confirmación del campo contraseña no coincide.')
        ->assertSee('El campo contraseña debe tener al menos 8 caracteres.');
});

it('describes the new password field with its hint', function () {
    $this->get(route('password.reset', ['token' => 'dummy-token']))
        ->assertSee('id="password-hint"', false)
        ->assertSee('Mínimo 8 caracteres.')
        ->assertSee('aria-describedby="password-hint"', false)
        ->assertDontSee('Solicitar un nuevo enlace');
});

it('offers a new link when the reset token is rejected', function () {
    $user = User::factory()->create(['email' => 'auditor@efectimedios.com']);
    $url = route('password.reset', ['token' => 'vencido', 'email' => $user->email]);

    $this->from($url)->post(route('password.update'), [
        'token' => 'vencido',
        'email' => $user->email,
        'password' => 'NuevaClave123',
        'password_confirmation' => 'NuevaClave123',
    ])->assertRedirect($url)->assertSessionHasErrors('email');

    $this->get($url)
        ->assertSee('El token de restablecimiento de contraseña es inválido.')
        ->assertSee('href="'.route('password.request').'"', false)
        ->assertSee('Solicitar un nuevo enlace')
        ->assertSee('aria-describedby="login-error-email"', false);
});
