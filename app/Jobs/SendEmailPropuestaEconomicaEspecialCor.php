<?php

namespace App\Jobs;

use Throwable;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\SendMailPropuestaPlanEspecial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class SendEmailPropuestaEconomicaEspecialCor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details = [];
    protected $group_collect = [];
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
    public function __construct($details, $group_collect, $user)
    {
        $this->details = $details;
        $this->group_collect = $group_collect;
        $this->user = $user;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $this->generatePDF($this->details, $this->group_collect);

        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 ' . $this->details['code'] . '.pdf ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/quotes/' . $this->details['code'] . '.pdf')
            ])
            ->sendToDatabase($this->user);
    }

    private function generatePDF($details, $group_collect)
    {
        ini_set('memory_limit', '2048M');

        /**
         * Logica para generar el pdf
         * ----------------------------------------------------------------------------------------------------
         */
        $pdf = Pdf::loadView('documents.propuesta-economica-cor', compact('details', 'group_collect'));
        $name_pdf = $details['code'] . '.pdf';
        $pdf->save(public_path('storage/quotes/' . $name_pdf));

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($details['email'])->send(new SendMailPropuestaPlanEspecial($details['name'], $name_pdf));
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendEmailPropuestaEconomicaEspecialCor: FAILED");
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);

        // Send user notification of failure, etc...

    }
}