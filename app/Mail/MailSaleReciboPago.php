<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailSaleReciboPago extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $ccRecipients
     */
    public function __construct(
        public string $invoiceNumber,
        public string $pdfPath,
        public array $ccRecipients = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibo de pago Nro. '.$this->invoiceNumber,
            cc: $this->ccRecipients,
        );
    }

    public function content(): Content
    {
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
