<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessAgentFichaPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $agentDisplayName,
        public string $agentCodeLabel,
        public string $pdfBinary,
        public string $attachmentFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ficha de agente — '.$this->agentCodeLabel.' · '.$this->agentDisplayName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.business-agent-ficha-pdf-html',
            with: [
                'agentDisplayName' => $this->agentDisplayName,
                'agentCodeLabel' => $this->agentCodeLabel,
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
