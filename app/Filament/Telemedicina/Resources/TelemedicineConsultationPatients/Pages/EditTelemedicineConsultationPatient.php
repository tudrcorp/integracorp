<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasInformAmdModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasMedicamentosStepInfoModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Services\TelemedicineSupplyConsumptionRecorder;
use App\Support\Telemedicine\TelemedicineInitialDiagnosisUpdater;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Edición de una consulta ya registrada.
 *
 * Editar **no** regenera los documentos del caso: para eso está
 * {@see \App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService}.
 * Aquí vivía un `afterCreate()` copiado de la página de creación que Filament
 * nunca llegó a llamar —`callHook('afterCreate')` solo existe en `CreateRecord`—
 * y que ni siquiera podía correr: invocaba un `sendNotifications()` inexistente
 * y una variable `$data` sin definir. De haberse "arreglado" moviéndolo a
 * `afterSave()` habría duplicado medicamentos y laboratorios en cada edición y
 * descontado el inventario dos veces. No reintroducirlo.
 */
class EditTelemedicineConsultationPatient extends EditRecord
{
    use HasInformAmdModal;
    use HasMedicamentosStepInfoModal;

    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getListeners(): array
    {
        return [
            'open-medicamentos-step-info-modal' => 'openMedicamentosStepInfoModal',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $caseId = (int) ($data['telemedicine_case_id'] ?? $this->getRecord()->telemedicine_case_id ?? 0);
        $status = (string) ($data['status'] ?? $this->getRecord()->status ?? '');

        if ($caseId > 0 && $status !== TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS) {
            $data = array_merge($data, TelemedicineInitialDiagnosisUpdater::formStateForCase($caseId));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TelemedicineInitialDiagnosisUpdater::mergeIntoConsultationFormData($data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof TelemedicineConsultationPatient) {
            app(TelemedicineSupplyConsumptionRecorder::class)
                ->recordAndNotify($record, $this->data['medical_supplies'] ?? []);
        }

        if ((string) $record->status === TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS) {
            return;
        }

        try {
            TelemedicineInitialDiagnosisUpdater::syncFromFollowUp(
                (int) $record->telemedicine_case_id,
                (string) ($record->diagnostic_impression ?? $this->data[TelemedicineInitialDiagnosisUpdater::FORM_FIELD] ?? ''),
                Auth::user() instanceof User ? Auth::user() : null,
                filled($record->code_reference) ? (string) $record->code_reference : null,
            );
        } catch (\Throwable $diagnosisException) {
            Log::error('Error al actualizar el diagnóstico principal de la consulta inicial: '.$diagnosisException->getMessage(), [
                'telemedicine_case_id' => $record->telemedicine_case_id,
                'telemedicine_consultation_id' => $record->id,
                'exception' => $diagnosisException,
            ]);

            Notification::make()
                ->title('No se pudo actualizar el diagnóstico principal')
                ->body('La consulta se guardó, pero el diagnóstico de la consulta inicial no se actualizó. Revise la bitácora e intente de nuevo.')
                ->danger()
                ->send();
        }
    }
}
