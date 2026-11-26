<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClinicalRecordAccessLog;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordAccess;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordPdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Genera el PDF de la ficha clínica y avisa en la campana con un enlace de descarga firmado.
 * Vuelve a comprobar el acceso: si el usuario lo perdió entre el clic y la cola, no se genera.
 */
class GenerateClinicalRecordPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $patientId,
        public int $userId,
        public string $panel,
        public ?string $ip = null,
        public string $kind = ClinicalRecordAccess::KIND_CLINICAL,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $patient = TelemedicinePatient::query()->find($this->patientId);

        if (! $user instanceof User || ! $patient instanceof TelemedicinePatient) {
            return;
        }

        $label = $this->kind === ClinicalRecordAccess::KIND_TECHNICAL ? 'ficha técnica' : 'ficha clínica';

        if (! ClinicalRecordAccess::canView($user, $patient, $this->panel, $this->kind)) {
            Notification::make()->title('No se generó el PDF de la '.$label)->body('Ya no tienes acceso a esta ficha del paciente.')->warning()->sendToDatabase($user);

            return;
        }

        $file = ClinicalRecordPdf::store($patient, (int) $user->getKey(), (string) $user->name, $this->kind);
        ClinicalRecordAccess::record($user, $patient, $this->panel, ClinicalRecordAccessLog::ACTION_PDF, $this->ip, $this->kind);

        Notification::make()
            ->title('PDF de la '.$label.' listo')
            ->body(mb_strtoupper((string) $patient->full_name).'. El enlace vence en '.ClinicalRecordPdf::KEEP_HOURS.' horas.')
            ->icon('heroicon-o-clipboard-document-list')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar PDF')
                    ->url(URL::temporarySignedRoute('clinical-record.pdf', now()->addHours(ClinicalRecordPdf::KEEP_HOURS), ['file' => $file]), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Ficha clínica: falló el PDF', ['patient' => $this->patientId, 'error' => $exception?->getMessage()]);

        $user = User::query()->find($this->userId);

        if ($user instanceof User) {
            Notification::make()->title('No se pudo generar el PDF de la ficha')->body('Intenta de nuevo. Si se repite, avisa a soporte.')->danger()->sendToDatabase($user);
        }
    }
}
