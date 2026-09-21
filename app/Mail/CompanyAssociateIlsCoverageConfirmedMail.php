<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CompanyAssociate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyAssociateIlsCoverageConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $emailPayload
     */
    public function __construct(
        public CompanyAssociate $associate,
        public array $emailPayload,
        public string $recipientEmail,
        public string $subjectLine,
        public ?string $voucherPath = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, 'Analista INTEGRACORP')],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.company-associate-ils-coverage-confirmed',
            with: $this->emailPayload,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->voucherPath === null || ! is_file($this->voucherPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->voucherPath)->as(basename($this->voucherPath)),
        ];
    }
}
