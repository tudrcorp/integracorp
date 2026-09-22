<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CorporatePaymentFrequencyChangeMail extends Mailable
{
    /**
     * @param  array{event: string, title: string, intro: string, generatedAt: string, changes: list<array<string, mixed>>}  $emailPayload
     */
    public function __construct(
        public array $emailPayload,
        public string $recipientEmail,
        public string $subjectLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, 'Administración INTEGRACORP')],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.corporate-payment-frequency-change',
            with: $this->emailPayload,
        );
    }
}
