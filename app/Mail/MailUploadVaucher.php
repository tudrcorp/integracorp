<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailUploadVaucher extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    /**
     * El número de veces que se reintentará el trabajo si falla.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * El número de segundos que hay que esperar antes de reintentar.
     *
     * @var int
     */
    public $backoff = [60, 300, 600]; // Reintentos exponenciales: 1m, 5m, 10m

    /**
     * Create a new message instance.
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Get the message envelope.
     * Aquí definimos quién envía el correo y el asunto.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('tudrgroup.info@gmail.com', 'TU DR. EN CASA'),
            subject: 'Carga de Comprobante de Pago, Agente: '.$this->name
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.agentAgency_uploadVaucher',
        );
    }

    /**
     * Gestión optimizada de errores críticos.
     */
    public function failed(Throwable $exception): void
    {
        // 1. Log estructurado con severidad crítica y contexto completo
        Log::critical('FALLA: Envío de correo de carga de comprobante de pago', [
            'destinatario' => [
                'nombre' => $this->name,
            ],
            'causa_error' => $exception->getMessage(),
            'codigo_error' => $exception->getCode(),
            'clase_error' => get_class($exception),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // 2. Lógica de contingencia (Opcional):
        // Podrías insertar esto en una tabla de 'envios_fallidos' para un reintento manual posterior
        /*
        DB::table('failed_birthday_emails')->insert([
            'client_name' => $this->name,
            'client_email' => $this->email,
            'error_log' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
        */

        // 3. Alerta a canales de monitoreo (Ej: Slack o Sentry)
        if (app()->environment('production')) {
            // report($exception); // Esto enviaría el error automáticamente a Sentry/Bugsnag
        }
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
}
