<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailNotificacionSolicitudCotizacion extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name_user;
    public $code;
    public $name_agent;

    /**
     * Create a new message instance.
     */
    public function __construct($full_name, $code, $name_agent)
    {
        $this->name_user = $full_name;
        $this->code = $code;
        $this->name_agent = $name_agent;
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
            view: 'mails.send-notificacion-solicitud-cotizacion',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}