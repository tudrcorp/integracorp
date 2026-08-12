<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TdevRegisteredAnalystMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $emailPayload
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
            to: [new Address($this->recipientEmail, 'Analista TDEV')],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.tdev-registered-analyst',
            with: $this->emailPayload,
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
