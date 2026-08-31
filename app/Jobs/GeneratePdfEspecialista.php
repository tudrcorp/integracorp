<?php

namespace App\Jobs;

use App\Services\NotificationTelemedicinaService;
use App\Support\Telemedicine\Concerns\LogsTelemedicineJobFailures;
use App\Support\Telemedicine\TelemedicineCaseDocumentReadyNotification;
use App\Support\Telemedicine\TelemedicineCoverageDocumentSplit;
use App\Support\Telemedicine\TelemedicineCoverageSplitPdfWriter;
use App\Support\Telemedicine\TelemedicineJobFailureLogger;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GeneratePdfEspecialista implements ShouldQueue
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
            foreach ($this->generatePDF($this->data) as $name_pdf) {
                TelemedicineCaseDocumentReadyNotification::send($this->user, $this->data, $name_pdf);
            }
        }, $this->telemedicineJobFailureContext());
    }

    /**
     * @return list<string>
     */
    private function generatePDF($data): array
    {
        ini_set('memory_limit', '2048M');

        $payload = is_array($data) ? $data : [];

        return TelemedicineCoverageSplitPdfWriter::write(
            (string) $this->type_document,
            'documents.especialista',
            'portrait',
            13,
            'ORDEN CONSULTA CON ESPECIALISTA',
            TelemedicineCoverageDocumentSplit::orderGroups('especialista', $payload),
            $payload,
        );
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
