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
 * Correo del aviso de cobro con el copy elegido por el analista (ya renderizado y saneado), el PDF
 * del aviso y, si el analista lo marcó, el PDF de métodos de pago.
 */
class CollectionNoticeCopyMail extends Mailable
{
    use Queueable, SerializesModels;

    public const PAYMENT_METHODS_FILENAME = 'Metodos-de-pago-TDG.pdf';

    /**
     * @param  list<string>  $ccRecipients
     * @param  list<string>  $bccRecipients  testigos en copia oculta (Centro de notificaciones)
     */
    public function __construct(
        public string $invoiceNumber,
        public string $noticePdfPath,
        public string $subjectLine,
        public string $bodyHtml,
        public array $ccRecipients = [],
        public ?string $paymentMethodsPdfPath = null,
        public array $bccRecipients = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            cc: $this->ccRecipients,
            bcc: $this->bccRecipients,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.document-copy',
            with: ['heading' => 'Aviso de cobro Nro. '.$this->invoiceNumber],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [
            Attachment::fromPath($this->noticePdfPath)
                ->as('ADP-'.$this->invoiceNumber.'.pdf')
                ->withMime('application/pdf'),
        ];

        if ($this->paymentMethodsPdfPath !== null) {
            $attachments[] = Attachment::fromPath($this->paymentMethodsPdfPath)
                ->as(self::PAYMENT_METHODS_FILENAME)
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
