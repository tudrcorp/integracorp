<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailCartaBienvenidaAgenteAgencia extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $name_pdf;
    public $id;

    /**
     * Create a new message instance.
     */
    public function __construct($id, $name, $name_pdf)
    {
        $this->id = $id;
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
            subject: '¡Bienvenido a TuDrEnCasa! 🎉',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.carta-bienvenida-agente',
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