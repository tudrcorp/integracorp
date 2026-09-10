<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMailKitBienvenida extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data = [];

    public $condicionado;

    /** @var list<string> */
    public array $attachmentPaths = [];

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $attachmentPaths  Rutas absolutas ya verificadas por WelcomeKitAttachments.
     */
    public function __construct($data, $condicionado = null, array $attachmentPaths = [])
    {
        $this->data = $data;
        $this->condicionado = $condicionado;
        $this->attachmentPaths = array_values($attachmentPaths);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kit Bienvenida',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.kit-bienvenida',
        );
    }

    /**
     * Solo se adjunta lo que existe en disco: un adjunto ausente hacía morir el job en el worker
     * y el correo nunca salía, mientras la UI ya había cantado éxito.
     *
     * @return array<int, string>
     */
    public function attachments(): array
    {
        $paths = $this->attachmentPaths !== []
            ? $this->attachmentPaths
            : $this->legacyAttachmentPaths();

        return array_values(array_filter(
            $paths,
            static fn ($path): bool => is_string($path) && $path !== '' && is_file($path),
        ));
    }

    /**
     * Rutas históricas, para las llamadas que aún no resuelven los adjuntos por su cuenta.
     *
     * @return array<int, string>
     */
    private function legacyAttachmentPaths(): array
    {
        $code = (string) ($this->data['code'] ?? '');

        if ($code === '') {
            return [];
        }

        $paths = [
            public_path('storage/certificados-doc/CER-'.$code.'.pdf'),
            public_path('storage/tarjeta-afiliacion/TAR-'.$code.'.pdf'),
        ];

        if (filled($this->condicionado)) {
            $paths[] = public_path('storage/condicionados/'.$this->condicionado);
        }

        return $paths;
    }

    /**
     * Deja rastro cuando el envío se encola y falla en el worker (fuera del try/catch del solicitante).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FALLA DE ENVIO: el kit de bienvenida falló en la cola.', [
            'code' => $this->data['code'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
