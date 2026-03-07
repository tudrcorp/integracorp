<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnulatedQuotesNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $anulatedCount
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address('cotizaciones@tudrencasa.com', 'Cotizaciones TuDrEnCasa')],
            subject: 'Reporte diario: Cotizaciones individuales anuladas (agencias, agentes)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.anulated-quotes-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
