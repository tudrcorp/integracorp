<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Ficha del afiliado enviada por correo desde el menú del afiliado. El PDF va
 * adjunto; Para, CC y CCO los pone el job.
 */
class AffiliateFichaMail extends Mailable
{
    public function __construct(
        public string $affiliateName,
        public string $affiliateDocument,
        public string $affiliationCode,
        public string $scopeLabel,
        public bool $includesClinical,
        public ?string $analystMessage,
        public ?string $senderName,
        public string $pdfBinary,
        public string $attachmentFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ficha del afiliado '.$this->affiliateName.' · '.$this->affiliationCode.' — Tu Doctor en Casa',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mails.affiliate-ficha');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfBinary, $this->attachmentFilename)->withMime('application/pdf'),
        ];
    }
}
