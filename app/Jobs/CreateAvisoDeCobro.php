<?php

namespace App\Jobs;

use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use App\Mail\SendMailPropuestaPlanIdeal;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;
use App\Mail\MailNotificacionSolicitudCotizacion;

class CreateAvisoDeCobro implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    protected $data = [];
    public $user;

    /**
     * Create a new job instance.
     */
    public function __construct($data, $user)
    {
        $this->data = $data;
        $this->user = $user;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {

            $this->generateAvisoDeCobro();

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        
    }

    private function generateAvisoDeCobro()
    {
        ini_set('memory_limit', '2048M');

        $data = $this->data;
        
        $name_pdf = 'ADP-' . $data['invoice_number'] . '.pdf';

        $pdf = Pdf::loadView('documents.aviso-de-cobro', compact('data'));
        $pdf->save(public_path('storage/avisoDeCobro/' . $name_pdf));

        Log::info('AVISO DE COBRO CREADO' . $name_pdf);
        Notification::make()
            ->title('¡AVISOS DE COBRO GENERADOS!')
            ->body('Los avisos de cobro fueron generados con exito.')
            ->success()
            ->sendToDatabase($this->user);
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("Job-CreateAvisoDeCobro: FAILED");
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Se produjo un error durante la generacion de los avisos de cobro. Por favor contacte con el Administrador del Sistema')
            ->danger()
            ->sendToDatabase($this->user);

    }
}