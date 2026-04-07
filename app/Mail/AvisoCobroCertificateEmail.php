<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvisoCobroCertificateEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $titular;

    public string $name_pdf;

    /**
     * Create a new message instance.
     */
    public function __construct(string $titular, string $name_pdf)
    {
        $this->titular = $titular;
        $this->name_pdf = $name_pdf;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso de cobro - TuDrEnCasa',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.sendCertificate',
            with: [
                'titular' => $this->titular,
            ],
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
            public_path('storage/avisoDeCobro/'.$this->name_pdf),
        ];
    }
}
