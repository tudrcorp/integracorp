<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IndividualQuoteFollowUpInternalCopyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $subjectLine,
        public string $allyName,
        public string $followUpLabel,
        public string $messageBody,
        public int $quoteCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, 'Control INTEGRACORP')],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.individual-quote-follow-up-internal-copy',
            with: [
                'allyName' => $this->allyName,
                'followUpLabel' => $this->followUpLabel,
                'messageBody' => $this->messageBody,
                'quoteCount' => $this->quoteCount,
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
