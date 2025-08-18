<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailUploadDataCorporate extends Mailable
{
    use Queueable, SerializesModels;

    public $name_pdf;
    public $name_agent;
    public $code;

    /**
     * Create a new message instance.
     */
    public function __construct($name_pdf, $name_agent, $code)
    {
        $this->name_pdf = $name_pdf;
        $this->name_agent = $name_agent;
        $this->code = $code;

    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            
            subject: 'Data Corporativa',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.upload-data-corporate',
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