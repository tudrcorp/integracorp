<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuditCompletionSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $counts
     */
    public function __construct(
        public array $counts,
        public string $recipientEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, 'Auditoría INTEGRACORP')],
            subject: 'Reporte diario de auditorías completas · INTEGRACORP',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.audit-completion-summary',
            with: [
                'counts' => $this->counts,
                'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            ],
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
