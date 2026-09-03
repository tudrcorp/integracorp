<?php

namespace App\Jobs;

use App\Mail\SendMailPropuestaPlanInicial;
use App\Models\OperationDocumentList;
use App\Models\TelemedicineConsultationPatient;
use App\Services\NotificationTelemedicinaService;
use App\Support\Telemedicine\Concerns\LogsTelemedicineJobFailures;
use App\Support\Telemedicine\TelemedicineCaseDocumentReadyNotification;
use App\Support\Telemedicine\TelemedicineConsultationUploadedDocuments;
use App\Support\Telemedicine\TelemedicineInformePdfRenderer;
use App\Support\Telemedicine\TelemedicineJobFailureLogger;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GeneratePdfInformeMedicoCorto implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, LogsTelemedicineJobFailures, Queueable, SerializesModels;

    protected $data = [];

    protected $user;

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
        $this->runWithTelemedicineFailureLogging(function (): void {
            $this->generatePDF($this->data);

            $name_pdf = $this->data['ci_patient'].'-'.$this->data['code_reference'].'-'.$this->type_document.'.pdf';

            TelemedicineCaseDocumentReadyNotification::send($this->user, $this->data, $name_pdf);
        }, $this->telemedicineJobFailureContext());
    }

    private function generatePDF($data)
    {
        $name_pdf = $data['ci_patient'].'-'.$data['code_reference'].'-'.$this->type_document.'.pdf';

        TelemedicineInformePdfRenderer::save(
            TelemedicineInformePdfRenderer::VIEW_CORTO,
            $data,
            'telemedicina-doc/'.$name_pdf,
        );

        $this->syncConsultationUploadedDocuments($data, $name_pdf);

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        // Mail::to($details['email'])->send(new SendMailPropuestaPlanInicial($details['name'], $name_pdf));
    }

    private function syncConsultationUploadedDocuments(array $data, string $namePdf): void
    {
        $consultationId = (int) ($data['telemedicine_consultation_id'] ?? 0);

        if ($consultationId <= 0) {
            return;
        }

        $consultation = TelemedicineConsultationPatient::query()->find($consultationId);

        if (! $consultation) {
            return;
        }

        $defaultDocumentTypeId = 14;
        $defaultDocumentTypeName = trim((string) OperationDocumentList::query()
            ->whereKey($defaultDocumentTypeId)
            ->value('name'));

        if ($defaultDocumentTypeName === '') {
            $defaultDocumentTypeName = 'INFORME MEDICO CONSULTA INICIAL (CORTO)';
        }

        // Reemplaza la entrada previa de este tipo en vez de acumularla: con
        // array_merge cada regeneración dejaba un duplicado más, y el catálogo
        // —que deduplica— acababa mostrando la copia más antigua, como si el
        // documento no se hubiese vuelto a generar.
        TelemedicineConsultationUploadedDocuments::sync($consultation, [
            'document_name' => $namePdf,
            'file_path' => 'telemedicina-doc/'.$namePdf,
            'document_type_ids' => [$defaultDocumentTypeId],
            'document_types' => [$defaultDocumentTypeName],
            'uploaded_at' => now()->toDateTimeString(),
        ], $defaultDocumentTypeId);
    }

    private function sendNotifications($data)
    {
        $masiveNotification = new NotificationTelemedicinaService;
        $masiveNotification->sendPreviewNotification($data['phone']);
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
        return TelemedicineJobFailureLogger::documentJobContext(
            is_array($this->data) ? $this->data : [],
            $this->user,
            $this->type_document !== null ? (string) $this->type_document : null,
        );
    }
}
