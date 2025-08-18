<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class MailLinkIndividualQuote extends Mailable
{
    use Queueable, SerializesModels;

    public $link;

    /**
     * Create a new message instance.
     */
    public function __construct($link)
    {
        $this->link = $link;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('comercial@tudrencasa.com', 'TuDrEnCasa Cotización Individual. (INTEGRACORP)'),
            subject: 'Link Interactivo de Cotización Individual',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.link_interactivo_individual_quote',
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