<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UploadPayment extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data = [];

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        //
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comprobante de pago '.$this->data['code'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.upload-payment',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Gestión optimizada de errores críticos.
     */
    public function failed(Throwable $exception): void
    {
        // 1. Log estructurado con severidad crítica y contexto completo
        Log::critical('FALLA: Envío de correo de carga de comprobante de pago', [
            'code' => [
                'code' => $this->data['code'],
            ],
            'causa_error' => $exception->getMessage(),
            'codigo_error' => $exception->getCode(),
            'clase_error' => get_class($exception),
            'timestamp' => now()->toDateTimeString(),
        ]);

    }
}
