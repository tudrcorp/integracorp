<?php

namespace App\Jobs;

use Throwable;
use App\Models\User;
use App\Mail\UploadPayment;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\SendMailPropuestaPlanInicial;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class SendEmailToAdministrationUploadPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $info = [];
    protected $user;

    /**
     * Número máximo de intentos.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Tiempo en segundos para esperar antes de reintentar (opcional).
     *
     * @var int
     */
    public $backoff = 3; // Espera 3 segundos entre intentos

    /**
     * Create a new job instance.
     */
    public function __construct($info, $user)
    {
        $this->info = $info;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->enviarNotificacion($this->info);

        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('La Notificación de carga de comprobante de pago se ha enviado correctamente al departamento de Administración.')
            ->success()
            ->sendToinfobase($this->user);
    }

    private function enviarNotificacion($info)
    {
        Log::info($info);
        Mail::to($info['email'])->send(new UploadPayment($info));
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendEmailToAdministrationUploadPayment: FAILED");
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error enviando la notificación de carga de comprobante de pago. Por favor, comuniquese con el administrador del Sistema.')
            ->danger()
            ->sendToinfobase($this->user);

        // Send user notification of failure, etc...

    }
}