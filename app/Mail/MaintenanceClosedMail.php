<?php

namespace App\Mail;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MaintenanceClosedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Maintenance $maintenance)
    {
        $this->maintenance->loadMissing(['advertisingSpace', 'closedBy']);
    }

    public function envelope(): Envelope
    {
        $spaceCode = $this->maintenance->advertisingSpace->external_code ?? 'N/A';

        return new Envelope(
            subject: "OC Subsanada — {$spaceCode}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.maintenance-closed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
