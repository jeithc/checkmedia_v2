<?php

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;

test('email can be sent', function () {
    Mail::fake();

    $response = $this->get('/test-email');

    $response->assertStatus(200);
    $response->assertSee('Correo enviado correctamente');

    Mail::assertSent(TestEmail::class, function ($mail) {
        return $mail->hasTo('jeith2@gmail.com');
    });
});
