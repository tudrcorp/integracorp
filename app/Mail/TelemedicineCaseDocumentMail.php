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

class TelemedicineCaseDocumentMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $patientName,
        public string $documentName,
        public string $relativePath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento de telemedicina — '.$this->documentName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.telemedicine-case-document',
            with: [
                'patientName' => $this->patientName,
                'documentName' => $this->documentName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $relativePath = ltrim($this->relativePath, '/');
        $filePath = Storage::disk('public')->path($relativePath);

        if (! is_file($filePath)) {
            Log::error('TELEMEDICINA: No se encontró el PDF para adjuntar al correo del caso.', [
                'path' => $filePath,
                'document_name' => $this->documentName,
            ]);

            return [];
        }

        return [
            Attachment::fromPath($filePath)
                ->as($this->documentName)
                ->withMime('application/pdf'),
        ];
    }
}
