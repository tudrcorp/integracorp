<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BirthdayNotificationSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $summaryMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, 'Cumpleaños INTEGRACORP')],
            subject: 'Resumen de tarjetas de cumpleaños · INTEGRACORP',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.birthday-notification-summary',
            with: [
                'summaryMessage' => $this->summaryMessage,
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
