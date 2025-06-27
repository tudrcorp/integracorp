<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReSendDocument extends Mailable
{
    use Queueable, SerializesModels;

    public $title, $name_ti, $name_pdf;

    /**
     * Create a new message instance.
     */
    public function __construct($title, $name_ti, $name_pdf)
    {
        $this->title = $title;
        $this->name_ti = $name_ti;
        $this->name_pdf = $name_pdf;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a TuDrEnCasa! 🎉',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.re-send-document',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            public_path('storage/' . $this->name_pdf),
            public_path('storage/metodos-pago-banca-extranjera-unificado.pdf'),
        ];
    }
}