<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessTravelAgencyFichaPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $travelAgencyDisplayName,
        public string $travelAgencyCodeLabel,
        public string $pdfBinary,
        public string $attachmentFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ficha de agencia de viajes — '.$this->travelAgencyCodeLabel.' · '.$this->travelAgencyDisplayName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.business-travel-agency-ficha-pdf-html',
            with: [
                'travelAgencyDisplayName' => $this->travelAgencyDisplayName,
                'travelAgencyCodeLabel' => $this->travelAgencyCodeLabel,
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
