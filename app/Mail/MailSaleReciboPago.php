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
 * Correo del recibo de pago con el PDF adjunto.
 *
 * Con `bodyHtml` usa el copy elegido por el analista (ya renderizado y saneado por
 * DocumentEmailTemplateRenderer); sin él conserva el texto histórico.
 */
class MailSaleReciboPago extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $ccRecipients
     * @param  list<string>  $bccRecipients  testigos en copia oculta (Centro de notificaciones)
     */
    public function __construct(
        public string $invoiceNumber,
        public string $pdfPath,
        public array $ccRecipients = [],
        public ?string $subjectLine = null,
        public ?string $bodyHtml = null,
        public array $bccRecipients = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: filled($this->subjectLine) ? (string) $this->subjectLine : 'Recibo de pago Nro. '.$this->invoiceNumber,
            cc: $this->ccRecipients,
            bcc: $this->bccRecipients,
        );
    }

    public function content(): Content
    {
        if (filled($this->bodyHtml)) {
            return new Content(
                view: 'mails.document-copy',
                with: ['heading' => 'Recibo de pago Nro. '.$this->invoiceNumber],
            );
        }

        return new Content(
            view: 'mails.aviso-de-pago',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('RDP-'.$this->invoiceNumber.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
