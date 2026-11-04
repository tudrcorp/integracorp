<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * No implementa ShouldQueue a propósito: siempre se envía desde SendCapitationPlanReportJob,
 * que ya corre en la cola de documentos (ver WhiteCompanySalesReportMail).
 */
class CapitationPlanReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $planNames
     */
    public function __construct(
        public string $documentTitle,
        public array $planNames,
        public ?string $customMessage,
        public string $attachmentPath,
        public string $attachmentName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu Dr Group — '.$this->documentTitle);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.capitationPlanReport',
            with: [
                'documentTitle' => $this->documentTitle,
                'planNames' => $this->planNames,
                'customMessage' => $this->customMessage,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! is_file($this->attachmentPath)) {
            return [];
        }

        return [Attachment::fromPath($this->attachmentPath)->as($this->attachmentName)->withMime('application/pdf')];
    }
}
