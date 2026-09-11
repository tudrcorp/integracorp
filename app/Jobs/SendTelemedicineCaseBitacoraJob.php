<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelemedicineCase;
use App\Models\User;
use App\Services\Telemedicine\TelemedicineCaseDocumentDeliveryService;
use App\Services\TelemedicineCaseBitacoraPdfService;
use App\Support\Operations\TelemedicineCaseBitacora;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTelemedicineCaseBitacoraJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 20, 60, 120, 300];
    }

    public function __construct(
        public int $caseId,
        public ?string $phone,
        public ?string $email,
        public int $requestedByUserId,
    ) {
        $this->onQueue('system');
    }

    public function handle(): void
    {
        $case = TelemedicineCase::query()->find($this->caseId);

        if (! $case instanceof TelemedicineCase) {
            return;
        }

        $relativePath = TelemedicineCaseBitacoraPdfService::ensure($case);
        $documentName = TelemedicineCaseBitacora::documentName($case);
        $patientName = (string) ($case->patient_name ?: $case->telemedicinePatient?->full_name ?: 'Paciente');

        $result = TelemedicineCaseDocumentDeliveryService::send(
            $relativePath,
            $documentName,
            $patientName,
            $this->phone,
            $this->email,
        );

        $channels = array_values(array_filter([
            $result['whatsapp_sent'] ? 'WhatsApp' : null,
            $result['email_sent'] ? 'correo electrónico' : null,
        ]));

        $user = User::query()->find($this->requestedByUserId);

        if (! $user instanceof User) {
            return;
        }

        $code = filled($case->code) ? (string) $case->code : 'Caso #'.$case->id;

        if ($channels === []) {
            Notification::make()
                ->title('No se pudo enviar la bitácora')
                ->body('Revise el teléfono o correo del caso '.$code.' e intente nuevamente.')
                ->danger()
                ->sendToDatabase($user);

            return;
        }

        Notification::make()
            ->title('Bitácora enviada')
            ->body('La bitácora del caso '.$code.' fue enviada por '.implode(' y ', $channels).'.')
            ->success()
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('OPERACIONES: Falló el envío de la bitácora de caso.', [
            'case_id' => $this->caseId,
            'message' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->requestedByUserId);

        if (! $user instanceof User) {
            return;
        }

        Notification::make()
            ->title('Error al enviar la bitácora')
            ->body('No se pudo generar o enviar la bitácora del caso. Intente nuevamente.')
            ->danger()
            ->sendToDatabase($user);
    }
}
