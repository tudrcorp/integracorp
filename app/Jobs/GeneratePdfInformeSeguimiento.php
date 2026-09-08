<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\Concerns\LogsTelemedicineJobFailures;
use App\Support\Telemedicine\TelemedicineCaseDocumentReadyNotification;
use App\Support\Telemedicine\TelemedicineConsultationUploadedDocuments;
use App\Support\Telemedicine\TelemedicineFollowUpReportDocument;
use App\Support\Telemedicine\TelemedicineInformePdfRenderer;
use App\Support\Telemedicine\TelemedicineJobFailureLogger;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GeneratePdfInformeSeguimiento implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, LogsTelemedicineJobFailures, Queueable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    protected mixed $user;

    protected string $type_document;

    public int $tries = 5;

    public int $backoff = 3;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct($data, $user, $type_document)
    {
        $this->data = is_array($data) ? $data : [];
        $this->user = $user;
        $this->type_document = (string) $type_document;
    }

    public function handle(): void
    {
        $this->runWithTelemedicineFailureLogging(function (): void {
            $this->generatePDF($this->data);

            $namePdf = $this->documentFileName($this->data);

            TelemedicineCaseDocumentReadyNotification::send($this->user, $this->data, $namePdf);
        }, $this->telemedicineJobFailureContext());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function generatePDF(array $data): void
    {
        $namePdf = $this->documentFileName($data);

        TelemedicineInformePdfRenderer::save(
            TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO,
            $data,
            'telemedicina-doc/'.$namePdf,
        );

        $this->syncConsultationUploadedDocuments($data, $namePdf);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function documentFileName(array $data): string
    {
        return $data['ci_patient'].'-'.$data['code_reference'].'-'.$this->type_document.'.pdf';
    }

    /**
     * @param  array<string, mixed>  $data
     */
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

        $documentTypeId = TelemedicineFollowUpReportDocument::documentTypeId();
        $documentTypeName = TelemedicineFollowUpReportDocument::DOCUMENT_TYPE_NAME;

        TelemedicineConsultationUploadedDocuments::sync($consultation, [
            'document_name' => $namePdf,
            'file_path' => 'telemedicina-doc/'.$namePdf,
            'document_type_ids' => [$documentTypeId],
            'document_types' => [$documentTypeName],
            'uploaded_at' => now()->toDateTimeString(),
        ], $documentTypeId);
    }

    public function failed(?Throwable $exception): void
    {
        $this->logTelemedicineJobFailure($exception, $this->telemedicineJobFailureContext());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error al generar el informe de seguimiento. Por favor, contacte con el administrador del sistema.')
            ->danger()
            ->sendToDatabase($this->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function telemedicineJobFailureContext(): array
    {
        return TelemedicineJobFailureLogger::documentJobContext(
            $this->data,
            $this->user,
            $this->type_document,
        );
    }
}
