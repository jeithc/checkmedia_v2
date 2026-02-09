<?php

namespace Tests\Feature;

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_email_can_be_sent(): void
    {
        Mail::fake();

        $response = $this->get('/test-email');

        $response->assertStatus(200);
        $response->assertSee('Correo enviado correctamente');

        Mail::assertSent(TestEmail::class, function ($mail) {
            return $mail->hasTo('jeith2@gmail.com');
        });
    }
}
