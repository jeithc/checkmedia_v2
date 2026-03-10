<?php

namespace App\Mail;

use App\Models\AdvertisingSpace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PreventiveReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $space;

    /**
     * Create a new message instance.
     */
    public function __construct(AdvertisingSpace $space)
    {
        $this->space = $space;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio Check Media: Mantenimiento Preventivo Próximo (' . $this->space->external_code . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.preventive-reminder',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
