<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StorefrontQuotePaymentReceiptMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $emailPayload
     */
    public function __construct(
        public array $emailPayload,
        public string $recipientEmail,
        public string $subjectLine,
        public ?string $receiptPath = null,
        public string $receiptFilename = 'comprobante',
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
            view: 'mails.storefront-quote-payment-receipt',
            with: $this->emailPayload,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->receiptPath === null || ! is_file($this->receiptPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->receiptPath)->as($this->receiptFilename),
        ];
    }
}
