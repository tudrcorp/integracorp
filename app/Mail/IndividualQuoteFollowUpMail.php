<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IndividualQuoteFollowUpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $attachmentRelativePaths  Rutas relativas al disco public (p. ej. imagenes-seguimiento-cotizaciones/img1.png)
     */
    public function __construct(
        public string $recipientEmail,
        public string $recipientName,
        public string $subjectLine,
        public string $followUpLabel,
        public string $messageBody,
        public string $audienceLabel,
        public array $attachmentRelativePaths = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, $this->recipientName)],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.individual-quote-follow-up',
            with: [
                'recipientName' => $this->recipientName,
                'followUpLabel' => $this->followUpLabel,
                'messageBody' => $this->messageBody,
                'audienceLabel' => $this->audienceLabel,
                'mediaFiles' => array_map(
                    fn (string $path): array => [
                        'name' => basename($path),
                        'url' => IndividualQuoteFollowUp::publicAssetUrl($path),
                    ],
                    $this->attachmentRelativePaths,
                ),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->attachmentRelativePaths as $relativePath) {
            $attachment = $this->resolveAttachment($relativePath);

            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    private function resolveAttachment(string $relativePath): ?Attachment
    {
        $relativePath = ltrim($relativePath, '/');
        $filename = basename($relativePath);

        $localPath = IndividualQuoteFollowUp::localPublicAssetPath($relativePath);

        if ($localPath !== null) {
            return Attachment::fromPath($localPath)->as($filename);
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Attachment::fromStorageDisk('public', $relativePath)->as($filename);
        }

        try {
            $response = Http::timeout(90)->get(IndividualQuoteFollowUp::publicAssetUrl($relativePath));

            if (! $response->successful() || $response->body() === '') {
                Log::warning('IndividualQuoteFollowUpMail: no se pudo descargar adjunto multimedia', [
                    'path' => $relativePath,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $contents = $response->body();

            return Attachment::fromData(fn (): string => $contents, $filename);
        } catch (Throwable $exception) {
            Log::warning('IndividualQuoteFollowUpMail: error al resolver adjunto multimedia', [
                'path' => $relativePath,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
