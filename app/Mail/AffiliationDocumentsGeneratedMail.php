<?php

namespace App\Mail;

use App\Support\Affiliations\AffiliationJobFailureLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AffiliationDocumentsGeneratedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $attachmentPaths
     */
    public function __construct(
        public string $titular,
        public array $attachmentPaths,
        public string $recipientName = 'Aliado estratégico',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documentos de afiliación — TuDrEnCasa',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.affiliationDocumentsGenerated',
            with: [
                'titular' => $this->titular,
                'recipientName' => $this->recipientName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $out = [];
        foreach ($this->attachmentPaths as $path) {
            if (is_file($path)) {
                $out[] = Attachment::fromPath($path)->as(basename($path));
            }
        }

        return $out;
    }

    public function failed(?Throwable $exception): void
    {
        AffiliationJobFailureLogger::definitive(static::class, $exception, [
            'action' => 'send-documents-email',
            'titular' => $this->titular,
            'recipient_name' => $this->recipientName,
            'attachment_paths' => $this->attachmentPaths,
            'missing_attachments' => array_values(array_filter(
                $this->attachmentPaths,
                static fn (string $path): bool => ! is_file($path),
            )),
        ]);
    }
}
