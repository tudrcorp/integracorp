<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliateCarnetIssuedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $affiliationCode,
        public string $carnetPath,
        public string $condicionadoPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Su carnet de afiliado — Tu Doctor en Casa',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.affiliate-carnet-issued',
            with: [
                'recipientName' => $this->recipientName,
                'affiliationCode' => $this->affiliationCode,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $out = [];

        if (is_file($this->carnetPath)) {
            $out[] = Attachment::fromPath($this->carnetPath)->as(basename($this->carnetPath));
        }

        if (is_file($this->condicionadoPath)) {
            $out[] = Attachment::fromPath($this->condicionadoPath)->as(basename($this->condicionadoPath));
        }

        return $out;
    }
}
