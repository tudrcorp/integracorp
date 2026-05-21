<?php

namespace App\Jobs;

use App\Mail\SendMailPropuestaPlanInicial;
use App\Models\OperationDocumentList;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GeneratePdfLaboratorio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $this->generatePDF($this->data);

        $name_pdf = $this->data['ci_patiente'].'-'.$this->data['code_reference'].'-'.$this->type_document.'.pdf';

        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 '.$name_pdf.'ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/telemedicina-doc/'.$name_pdf),
            ])
            ->sendToDatabase($this->user);
    }

    private function generatePDF($data)
    {
        ini_set('memory_limit', '2048M');

        $pdf = Pdf::loadView('documents.laboratorios', compact('data'));
        $name_pdf = $data['ci_patiente'].'-'.$data['code_reference'].'-'.$this->type_document.'.pdf';
        $pdf->save(public_path('storage/telemedicina-doc/'.$name_pdf));

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

        $defaultDocumentTypeId = 11;
        $defaultDocumentTypeName = trim((string) OperationDocumentList::query()
            ->whereKey($defaultDocumentTypeId)
            ->value('name'));

        if ($defaultDocumentTypeName === '') {
            $defaultDocumentTypeName = 'ORDEN PARA LABORATORIOS';
        }

        $existingDocuments = is_array($consultation->uploaded_documents)
            ? $consultation->uploaded_documents
            : [];

        $newDocument = [
            'document_name' => $namePdf,
            'file_path' => 'telemedicina-doc/'.$namePdf,
            'document_type_ids' => [$defaultDocumentTypeId],
            'document_types' => [$defaultDocumentTypeName],
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $consultation->update([
            'uploaded_documents' => array_values(array_merge($existingDocuments, [$newDocument])),
        ]);
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info('GeneratePdfLaboratorio: FAILED');
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación la Referencia. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);

        // Send user notification of failure, etc...

    }
}
