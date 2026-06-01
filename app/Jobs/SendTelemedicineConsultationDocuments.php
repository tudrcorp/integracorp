<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Services\TelemedicineConsultationDocumentsNotificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTelemedicineConsultationDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 3;

    public function __construct(
        protected int $consultationId,
        protected string $patientPhone,
        protected ?string $patientEmail,
        protected string $patientName,
        protected int $userId,
    ) {}

    public function handle(): void
    {
        $consultation = TelemedicineConsultationPatient::query()->find($this->consultationId);

        if ($consultation === null) {
            Log::warning('TELEMEDICINA: No se encontró la consulta para enviar documentos.', [
                'consultation_id' => $this->consultationId,
            ]);

            return;
        }

        $uploadedDocuments = is_array($consultation->uploaded_documents)
            ? $consultation->uploaded_documents
            : [];

        $pdfFilenames = collect($uploadedDocuments)
            ->pluck('document_name')
            ->filter(fn ($name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        TelemedicineConsultationDocumentsNotificationService::notify(
            $this->patientPhone,
            $this->patientEmail,
            $this->patientName,
            $pdfFilenames,
        );

        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('Los documentos de la consulta fueron enviados al paciente por WhatsApp y correo electrónico.')
            ->success()
            ->actions([
                Action::make('view_consultation')
                    ->label('Ver consulta')
                    ->url('/telemedicina/telemedicine-consultation-patients/'.$this->consultationId),
            ])
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendTelemedicineConsultationDocuments: FAILED', [
            'consultation_id' => $this->consultationId,
            'message' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error al enviar los documentos de la consulta al paciente. Por favor, contacte con el administrador del sistema.')
            ->danger()
            ->sendToDatabase($user);
    }
}
