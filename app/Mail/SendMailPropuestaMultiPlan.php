<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailables\Attachment;

class SendMailPropuestaMultiPlan extends Mailable
{
    use Queueable, SerializesModels;

    public $titular;
    public $name_pdf;

    /**
     * Create a new message instance.
     */
    public function __construct($titular, $name_pdf)
    {
        $this->titular = $titular;
        $this->name_pdf = $name_pdf;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('cotizaciones@tudrencasa.com', 'TuDrEnCasa Cotización'),
            subject: "Propuesta Sr(a). {$this->titular} - Cotización MultiPlan",
            tags: ['cotizacion', 'multi-plan'],
            metadata: [
                'titular_name' => $this->titular,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.send-propuesta-economica-planes-individuales',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $filePath = public_path("storage/quotes/{$this->name_pdf}");

        // Validación experta: Si el archivo no existe, registramos el error y enviamos sin adjunto
        // o podrías lanzar una excepción si el adjunto es obligatorio para el negocio.
        if (!file_exists($filePath)) {
            Log::error("Mailable Error: No se encontró el archivo para adjuntar.", [
                'path'    => $filePath,
                'titular' => $this->titular
            ]);

            return [];
        }

        return [
            Attachment::fromPath($filePath)
                ->as("Cotizacion_{$this->titular}.pdf")
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Manejo de errores cuando el Mailable falla después de los reintentos en la cola.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("Fallo definitivo enviando correo de propuesta a: {$this->titular}", [
            'error' => $exception->getMessage(),
            'file'  => $this->name_pdf
        ]);
    }
}