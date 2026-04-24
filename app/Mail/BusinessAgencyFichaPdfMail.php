<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessAgencyFichaPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $agencyDisplayName,
        public string $agencyCodeLabel,
        public string $pdfBinary,
        public string $attachmentFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ficha de agencia — '.$this->agencyCodeLabel.' · '.$this->agencyDisplayName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.business-agency-ficha-pdf-html',
            with: [
                'agencyDisplayName' => $this->agencyDisplayName,
                'agencyCodeLabel' => $this->agencyCodeLabel,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfBinary, $this->attachmentFilename)
                ->withMime('application/pdf'),
        ];
    }
}
