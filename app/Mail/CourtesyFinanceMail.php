<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourtesyFinanceMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $documentLabel,
        public array $details,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Servicio por CORTESÍA — '.$this->documentLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.courtesy-finance',
            with: [
                'documentLabel' => $this->documentLabel,
                'details' => $this->details,
            ],
        );
    }
}
