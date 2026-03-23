<?php

namespace App\Mail;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuditBadCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Audit $audit)
    {
        $this->audit->loadMissing(['space', 'user']);
    }

    public function envelope(): Envelope
    {
        $spaceCode = $this->audit->space->external_code ?? 'N/A';

        return new Envelope(
            subject: "Auditoría con Error — {$spaceCode}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.audit-bad-created',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
