<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendMailKitBienvenida extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data = [];
    public $condicionado;

    /**
     * Create a new message instance.
     */
    public function __construct($data, $condicionado)
    {
        $this->data = $data;
        $this->condicionado = $condicionado;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kit Bienvenida',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.kit-bienvenida',
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
            public_path('storage/certificados-doc/' . 'CER-' . $this->data['code'] . '.pdf'),
            public_path('storage/tarjeta-afiliacion/' . 'TAR-' . $this->data['code'] . '.pdf'),
            public_path('storage/condicionados/' . $this->condicionado),
            
            // $this->attachFromStorage('public/ejemploCSV.csv', 'ejemploCSV.csv'),
        ];
    }
}