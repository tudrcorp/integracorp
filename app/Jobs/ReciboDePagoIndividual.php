<?php

namespace App\Jobs;

use App\Mail\MailAvisoDePago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

class ReciboDePagoIndividual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El Job se reintentará 3 veces con un retraso exponencial.
     */
    public int $tries = 3;

    /**
     * Segundos a esperar antes de reintentar.
     */
    public array $backoff = [60, 300, 600];

    /**
     * Tiempo máximo que el Job puede ejecutarse (segundos).
     */
    public int $timeout = 120;

    /**
     * Eliminar el job si el modelo ya no existe.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param array{invoice_number: string, email_ti: string} $data
     */
    public function __construct(protected array $data)
    {
        // En Laravel 12 usamos constructor promotion para mayor limpieza
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Evitamos usar ini_set en runtime si es posible; si el PDF es masivo, 
        // es mejor delegar a un proceso con memoria asignada o optimizar el HTML/CSS.

        $filename = "reciboDePago/RDP-{$this->data['invoice_number']}.pdf";
        $fullPath = storage_path("app/public/{$filename}");

        try {
            // 2. Generación del PDF con manejo de buffers
            $pdf = Pdf::loadView('documents.aviso-de-pago', [
                'data' => $this->data
            ]);

            // 3. Uso de Storage Facade en lugar de public_path directo
            // Esto permite cambiar a S3 en el futuro sin tocar el Job.
            Storage::disk('public')->put($filename, $pdf->output());

            // 4. Verificación de existencia antes de enviar correo
            if (!Storage::disk('public')->exists($filename)) {
                throw new \Exception("No se pudo generar o encontrar el archivo PDF: {$filename}");
            }

            // 5. Envío de correo con el nombre del PDF
            /** Se comenta esta linea a peticion del area administrativa */
            //Mail::to($this->data['email_ti'])->send(new MailAvisoDePago($filename));

        } catch (Throwable $e) {
            $this->handleFailure($e);
            throw $e; // Re-lanzamos para que el sistema de colas gestione el 'tries'
        }
    }

    /**
     * Manejo centralizado de logs y alertas.
     */
    protected function handleFailure(Throwable $e): void
    {
        Log::error("ADMINISTRACION: Error en ReciboDePagoIndividual [Invoice: {$this->data['invoice_number']}]: " . $e->getMessage(), [
            'exception' => $e,
            'data' => $this->data
        ]);
    }

    /**
     * Acción final si el Job falla después de todos los reintentos.
     */
    public function failed(Throwable $exception): void
    {
        // Aquí podrías enviar una notificación al admin o marcar la factura como "Error de envío" en DB
        Log::critical("ADMINISTRACION: El Job ReciboDePagoIndividual falló definitivamente para la factura: {$this->data['invoice_number']}");
    }
}
