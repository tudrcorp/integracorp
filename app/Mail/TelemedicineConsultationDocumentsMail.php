<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TelemedicineConsultationDocumentsMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<int, string>  $pdfFilenames
     */
    public function __construct(
        public string $patientName,
        public array $pdfFilenames,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documentos de su consulta de telemedicina — Tu Dr. en Casa',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.telemedicine-consultation-documents',
            with: [
                'patientName' => $this->patientName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->pdfFilenames as $filename) {
            $filePath = public_path('storage/telemedicina-doc/'.$filename);

            if (! is_file($filePath)) {
                Log::error('TELEMEDICINA: No se encontró el PDF para adjuntar al correo.', [
                    'path' => $filePath,
                    'patient_name' => $this->patientName,
                ]);

                continue;
            }

            $attachments[] = Attachment::fromPath($filePath)
                ->as($filename)
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
