<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContadoQuotePaymentMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $quoteNumber,
        public array $details,
        public ?string $relativePath = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pago de CONTADO — Cotización '.$this->quoteNumber,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.contado-quote-payment',
            with: [
                'quoteNumber' => $this->quoteNumber,
                'details' => $this->details,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! filled($this->relativePath)) {
            return [];
        }

        $relativePath = ltrim((string) $this->relativePath, '/');
        $filePath = Storage::disk('public')->path($relativePath);

        if (! is_file($filePath)) {
            Log::error('CONTADO: No se encontró el PDF para adjuntar al correo de pago de contado.', [
                'path' => $filePath,
                'quote_number' => $this->quoteNumber,
            ]);

            return [];
        }

        return [
            Attachment::fromPath($filePath)
                ->as('cotizacion-'.$this->quoteNumber.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
