<?php

namespace App\Jobs;

use Throwable;
use Filament\Actions\Action;
use App\Mail\CertificateEmail;
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

class GenerateCertificateCorporate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;
    public $afiliates;
    public $user;
    public $name_pdf;

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
    public function __construct($data, $afiliates, $user, $name_pdf)
    {
        $this->data = $data;
        $this->afiliates = $afiliates;
        $this->user = $user;
        $this->name_pdf = $name_pdf;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->generatePDF();

        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 El Certificado de afiliación fue generado con exito')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/certificates/' . $this->name_pdf)
            ])
            ->sendToDatabase($this->user);
    }

    private function generatePDF()
    {
        ini_set('memory_limit', '2048M');

        $data       = $this->data;
        $afiliates  = $this->afiliates;

        Log::info($data);
        Log::info($afiliates);

        $pdf = Pdf::loadView('documents.certificateCorporate', compact('data', 'afiliates'));
        $pdf->save(public_path('storage/certificates/' . $this->name_pdf));

        /**
         * Despues de guardar el certificado lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($data['email'])->send(new CertificateEmail($data['name_corporate'], $afiliates, $this->name_pdf));
        //
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendEmailPropuestaEconomicaMultiple: FAILED");
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);

        // Send user notification of failure, etc...

    }

}