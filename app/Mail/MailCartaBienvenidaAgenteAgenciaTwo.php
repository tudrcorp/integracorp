<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailCartaBienvenidaAgenteAgenciaTwo extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $name_pdf;
    public $code;

    /**
     * Create a new message instance.
     */
    public function __construct($code, $name, $name_pdf)
    {
        $this->code = $code;
        $this->name = $name;
        $this->name_pdf = $name_pdf;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('comercial@tudrencasa.com', 'TuDrEnCasa. Registro de Agencia!. (INTEGRACORP)'),
            subject: 'Bienvenida.! Agencia: ' . $this->name
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.carta-bienvenida-agencia',
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
        ];
    }
}