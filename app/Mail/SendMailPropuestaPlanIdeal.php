<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendMailPropuestaPlanIdeal extends Mailable implements ShouldQueue
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
            subject: '¡Bienvenido a TuDrEnCasa! 🎉',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.send-propuesta-economica-plan-ideal',
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
            public_path('storage/'.$this->name_pdf),
            // $this->attachFromStorage('public/ejemploCSV.csv', 'ejemploCSV.csv'),
        ];
    }
}