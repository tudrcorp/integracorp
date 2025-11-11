<?php

namespace App\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\NotificationTelemedicinaService;

use Throwable;
use App\Models\User;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Models\TelemedicineDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\SendMailPropuestaPlanInicial;
use Illuminate\Foundation\Bus\Dispatchable;
use function PHPUnit\Framework\assertNotTrue;
use App\Http\Controllers\NotificationController;

class GeneratePdfInformeMedicoLargo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data = [];
    protected $user;

    /**
     * Tipo de documento
     * Esto es para saber si el documento es de Consuta Inicila o de un Seguimiento
     * @var string
     * 
     */
    protected $type_document;

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
    public function __construct($data, $user, $type_document)
    {
        $this->data = $data;
        $this->user = $user;
        $this->type_document = $type_document;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->generatePDF($this->data);

        $name_pdf = $this->data['ci_patient'] . '-' . $this->data['code_reference'] . '-' . $this->type_document . '.pdf';

        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 ' . $name_pdf . 'ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/telemedicina-doc/' . $name_pdf)
            ])
            ->sendToDatabase($this->user);
    }

    private function generatePDF($data)
    {
        ini_set('memory_limit', '2048M');

        $pdf = Pdf::loadView('documents.informe-medico-largo', compact('data'));
        $name_pdf = $data['ci_patient'] . '-' . $data['code_reference'] . '-' . $this->type_document . '.pdf';
        $pdf->save(public_path('storage/telemedicina-doc/' . $name_pdf));

        /**
         * Creamos el documento en la base de datos
         * ---------------------------------------------------------------------------------------------------- 
         */
        $create_document = new TelemedicineDocument();
        $create_document->telemedicine_case_id          = $data['telemedicine_case_id'];
        $create_document->telemedicine_consultation_id  = $data['telemedicine_consultation_id'];
        $create_document->telemedicine_patient_id       = $data['telemedicine_patient_id'];
        $create_document->name                          = $name_pdf;
        $create_document->save();

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        // Mail::to($details['email'])->send(new SendMailPropuestaPlanInicial($details['name'], $name_pdf));
    }

    private function sendNotifications($data)
    {
        $masiveNotification = new NotificationTelemedicinaService();
        $masiveNotification->sendPreviewNotification($data['phone']);
    }


    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("GeneratePdfInformeMedicoLargo: FAILED");
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación la Referencia. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);

        // Send user notification of failure, etc...

    }
}