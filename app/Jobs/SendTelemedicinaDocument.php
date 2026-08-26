<?php

namespace App\Jobs;

use App\Services\NotificationTelemedicinaService;
use App\Support\Telemedicine\Concerns\LogsTelemedicineJobFailures;
use App\Support\Telemedicine\TelemedicineJobFailureLogger;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SendTelemedicinaDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, LogsTelemedicineJobFailures, Queueable, SerializesModels;

    protected $patient_id;

    protected $case_id;

    protected $user;

    protected $phone;

    /**
     * Tipo de documento
     * Esto es para saber si el documento es de Consuta Inicila o de un Seguimiento
     *
     * @var string
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
        $this->patient_id = $patient_id;
        $this->case_id = $case_id;
        $this->user = $user;
        $this->phone = $phone;
        $this->type_document = $type_document;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->runWithTelemedicineFailureLogging(function (): void {
            $this->documents($this->patient_id, $this->case_id, $this->phone, $this->type_document);

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 Los documentos fueron enviados al paciente de forma exitosa!')
                ->success()
                ->sendToDatabase($this->user);

            Cache::pull('jobResponse');
        }, $this->telemedicineJobFailureContext());
    }

    private function documents($patient_id, $case_id, $phone, $type_document)
    {
        $masiveNotification = new NotificationTelemedicinaService;
        $masiveNotification->sendDocuments($patient_id, $case_id, $phone, $type_document);
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        $this->logTelemedicineJobFailure($exception, $this->telemedicineJobFailureContext());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación la Referencia. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function telemedicineJobFailureContext(): array
    {
        return [
            'telemedicine_patient_id' => $this->patient_id,
            'telemedicine_case_id' => $this->case_id,
            'phone' => $this->phone,
            'type_document' => $this->type_document,
            'user_id' => TelemedicineJobFailureLogger::resolveUserId($this->user),
        ];
    }
}
