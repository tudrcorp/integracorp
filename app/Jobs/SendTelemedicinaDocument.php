<?php

namespace App\Jobs;

use Throwable;
use App\Models\User;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Models\TelemedicineDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\SendMailPropuestaPlanInicial;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use function PHPUnit\Framework\assertNotTrue;

use App\Http\Controllers\NotificationController;
use App\Services\NotificationTelemedicinaService;

class SendTelemedicinaDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $patient_id;
    protected $case_id;
    protected $user;
    protected $phone;

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
    public function __construct($patient_id, $case_id, $user, $phone, $type_document)
    {
        $this->patient_id       = $patient_id;
        $this->case_id          = $case_id;
        $this->user             = $user;
        $this->phone            = $phone;
        $this->type_document    = $type_document;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->documents($this->patient_id, $this->case_id, $this->phone, $this->type_document);

        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 Los documentos fueron enviados al paciente de forma exitosa!')
            ->success()
            ->sendToDatabase($this->user);

        Cache::pull('jobResponse');

    }

    private function documents($patient_id, $case_id, $phone, $type_document)
    {
        $masiveNotification = new NotificationTelemedicinaService();
        $masiveNotification->sendDocuments($patient_id, $case_id, $phone, $type_document);
    }


    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("GeneratePdfImagenologia: FAILED");
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación la Referencia. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);

        // Send user notification of failure, etc...

    }
}