<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class SendMailPropuestaPlanEspecial extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $titular;
    public $name_pdf;

    /**
     * Create a new message instance.
     */
    public function __construct($titular, $name_pdf)
    {
        $this->titular = $titular;
        $this->name_pdf = $name_pdf;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('cotizaciones@tudrencasa.com', 'TuDrEnCasa Cotización. (INTEGRACORP)'),
            subject: 'Propuesta Sr(a). ' .$this->titular. ' Cotización Plan Especial' //TODO: debo agregar los planes cotizados
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.send-propuesta-economica-plan-especial',
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
            public_path('storage/individual-quotes/' . $this->name_pdf),
        ];
    }
}