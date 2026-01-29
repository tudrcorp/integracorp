<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LogController;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppBirthdayNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de veces que se reintentará el trabajo si falla.
     */
    public int $tries = 3;

    /**
     * Segundos a esperar antes de reintentar.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $name,
        public string $phone,
        public string $content,
        public string $file,
        public string $type,
        public bool $isControlCopy = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            NotificationController::notificationBirthday(
                $this->name,
                $this->phone,
                $this->content,
                $this->file,
                $this->type
            );

            // Log de éxito solo si no es la copia de control para no saturar
            if (!$this->isControlCopy && class_exists(LogController::class)) {
                LogController::logSuccessWp($this->phone);
            }

            Log::info("Job Success: Envío de cumpleaños finalizado", [
                'para' => $this->phone,
                'tipo' => $this->isControlCopy ? 'Copia Control' : 'Cliente'
            ]);
            
        } catch (Throwable $e) {
            Log::error("Error en BirthdayNotificationJob para {$this->phone}: " . $e->getMessage());

            // Permitimos que el worker reintente según la propiedad $tries
            throw $e;
        }
    }

    /**
     * Manejo de fallo definitivo.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical("Fallo permanente en Job de Cumpleaños: {$this->phone}", [
            'error' => $exception->getMessage()
        ]);
    }
}
