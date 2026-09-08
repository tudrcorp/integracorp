<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasInformAmdModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasMedicamentosStepInfoModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Jobs\GeneratePdfInformeSeguimiento;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Services\TelemedicineSupplyConsumptionRecorder;
use App\Support\Telemedicine\TelemedicineFollowUpReportDocument;
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
 * Editar **no** regenera recetas ni órdenes clínicas: para eso está
 * {@see \App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService}.
 * El informe de seguimiento sí se vuelve a generar: es el documento de este
 * acto clínico y debe quedar alineado con el diagnóstico, la historia actual
 * y la evolución que el médico acaba de guardar.
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

        if (! $record instanceof TelemedicineConsultationPatient) {
            return;
        }

        app(TelemedicineSupplyConsumptionRecorder::class)
            ->recordAndNotify($record, $this->data['medical_supplies'] ?? []);

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

        $this->dispatchFollowUpReportDocument($record);
    }

    private function dispatchFollowUpReportDocument(TelemedicineConsultationPatient $record): void
    {
        if (! TelemedicineFollowUpReportDocument::appliesTo((string) $record->status)) {
            return;
        }

        try {
            $payload = TelemedicineFollowUpReportDocument::payloadFromSavedConsultation(
                $record,
                Auth::user() instanceof User ? Auth::user() : null,
            );

            if ($payload === null) {
                throw new \RuntimeException('No se encontró el médico o el paciente para firmar el informe de seguimiento.');
            }

            GeneratePdfInformeSeguimiento::dispatch(
                $payload,
                Auth::user(),
                TelemedicineFollowUpReportDocument::TYPE_DOCUMENT,
            );
        } catch (\Throwable $exception) {
            Log::error('Error al generar el informe de seguimiento: '.$exception->getMessage(), [
                'telemedicine_case_id' => $record->telemedicine_case_id,
                'telemedicine_consultation_id' => $record->id,
                'exception' => $exception,
            ]);

            Notification::make()
                ->title('No se pudo generar el informe de seguimiento')
                ->body('El seguimiento se guardó, pero el PDF no se encoló. Intente de nuevo o use «Generar documentos» en el caso.')
                ->danger()
                ->send();
        }
    }
}
