<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Respaldo por correo de una notificación interna que no pudo salir por Telegram
 * (o por WhatsApp, en los avisos críticos).
 */
class InternalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $titleLine,
        public string $bodyText,
        public bool $isFallback = true,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $this->titleLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.internal-notification',
            with: [
                'titleLine' => $this->titleLine,
                'bodyText' => $this->bodyText,
                'isFallback' => $this->isFallback,
            ],
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        if (blank($this->attachmentPath) || ! is_file((string) $this->attachmentPath)) {
            return [];
        }

        return [
            Attachment::fromPath((string) $this->attachmentPath)
                ->as((string) ($this->attachmentName ?: basename((string) $this->attachmentPath)))
                ->withMime('application/pdf'),
        ];
    }
}
