<?php

namespace App\Mail;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MaintenanceRequestedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Maintenance $maintenance)
    {
        $this->maintenance->loadMissing(['advertisingSpace', 'requestedBy']);
    }

    public function envelope(): Envelope
    {
        $spaceCode = $this->maintenance->advertisingSpace->external_code ?? 'N/A';

        return new Envelope(
            subject: "Nueva Novedad — {$spaceCode}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.maintenance-requested',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
