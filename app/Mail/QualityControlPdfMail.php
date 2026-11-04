<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\QualityControlPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * PDF de Quality Control (ficha o informe) adjunto. Se envía desde
 * {@see \App\Jobs\SendQualityControlPdfJob}, ya en cola.
 */
class QualityControlPdfMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $documentTitle,
        public string $storagePath,
        public string $attachmentName,
        public ?string $senderName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->documentTitle.' — Tu Doctor en Casa');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.quality-control-pdf',
            with: [
                'documentTitle' => $this->documentTitle,
                'senderName' => $this->senderName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(QualityControlPdfService::DISK, $this->storagePath)
                ->as($this->attachmentName)
                ->withMime('application/pdf'),
        ];
    }
}
