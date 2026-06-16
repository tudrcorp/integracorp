<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineAmdInformFormSchema;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDocument;
use App\Support\Telemedicine\TelemedicineAmdFileRegistrar;
use App\Support\Telemedicine\TelemedicineAmdInformRegistrar;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;

trait HasInformAmdModal
{
    public ?int $pendingAmdInformId = null;

    /** @var array<int, int> */
    public array $pendingAmdDocumentIds = [];

    public function openInformAmdModal(): void
    {
        $this->mountAction('informAmd');
    }

    protected function informAmdAction(): Action
    {
        return Action::make('informAmd')
            ->label('Informar AMD')
            ->modalHeading('Informe AMD — Consulta Inicial')
            ->modalDescription(new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Complete los datos clínicos de la consulta inicial. Al guardar se generará el Informe Médico Largo. Puede continuar con la consulta después.</p>'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Guardar y generar PDF')
            ->form(TelemedicineAmdInformFormSchema::components())
            ->fillForm(fn (): array => $this->informAmdFormDefaults())
            ->action(function (array $data): void {
                $formState = $this->form->getState();
                $serviceId = (int) ($formState['telemedicine_service_list_id'] ?? 0);

                if ($serviceId !== TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID) {
                    Notification::make()
                        ->title('Servicio no válido')
                        ->body('El informe AMD solo aplica cuando el servicio principal es AMD.')
                        ->warning()
                        ->send();

                    return;
                }

                $consultation = $this->resolveConsultationForInformAmd();

                if ($consultation instanceof TelemedicineConsultationPatient) {
                    TelemedicineAmdInformRegistrar::register($consultation, $data);
                } else {
                    $context = $this->informAmdPendingContext($formState);
                    $inform = TelemedicineAmdInformRegistrar::registerPending(
                        context: $context,
                        clinicalData: $data,
                        existingInformId: $this->pendingAmdInformId ?? session()->get(TelemedicineAmdInformRegistrar::SESSION_PENDING_INFORM_ID),
                    );

                    $this->pendingAmdInformId = $inform->id;
                    session()->put(TelemedicineAmdInformRegistrar::SESSION_PENDING_INFORM_ID, $inform->id);
                }

                $this->form->fill(array_merge($formState, $data));

                Notification::make()
                    ->title('Informe AMD registrado')
                    ->body($consultation
                        ? 'El Informe Médico Largo se está generando y aparecerá en los documentos del caso.'
                        : 'El Informe Médico Largo se está generando. Puede continuar con la consulta; al guardarla se vinculará automáticamente al caso.')
                    ->success()
                    ->send();
            });
    }

    protected function uploadAmdFileAction(): Action
    {
        return Action::make('uploadAmdFile')
            ->label('Cargar Archivo AMD')
            ->modalHeading('Cargar Archivo AMD')
            ->modalDescription(new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Adjunte una imagen o archivo relacionado con la AMD. Quedará disponible en los documentos del caso.</p>'))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar archivo')
            ->form([
                FileUpload::make('amd_file')
                    ->label('Archivo o imagen')
                    ->helperText('Formatos admitidos: imágenes, PDF y documentos de Office. Máximo 15 MB.')
                    ->directory('telemedicina-doc')
                    ->disk('public')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'image/bmp',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->maxSize(15360)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $formState = $this->form->getState();
                $serviceId = (int) ($formState['telemedicine_service_list_id'] ?? 0);

                if ($serviceId !== TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID) {
                    Notification::make()
                        ->title('Servicio no válido')
                        ->body('La carga de archivos AMD solo aplica cuando el servicio principal es AMD.')
                        ->warning()
                        ->send();

                    return;
                }

                $context = $this->informAmdPendingContext($formState);
                $caseId = (int) ($context['telemedicine_case_id'] ?? 0);
                $patientId = (int) ($context['telemedicine_patient_id'] ?? 0);

                if ($caseId <= 0 || $patientId <= 0) {
                    Notification::make()
                        ->title('Caso no disponible')
                        ->body('No se pudo identificar el caso o el paciente para guardar el archivo.')
                        ->warning()
                        ->send();

                    return;
                }

                $consultation = $this->resolveConsultationForInformAmd();
                $document = TelemedicineAmdFileRegistrar::register(
                    caseId: $caseId,
                    patientId: $patientId,
                    fileState: $data['amd_file'] ?? null,
                    consultationId: $consultation?->id,
                );

                if (! $document instanceof TelemedicineDocument) {
                    Notification::make()
                        ->title('Archivo no guardado')
                        ->body('No se pudo procesar el archivo cargado. Intente nuevamente.')
                        ->danger()
                        ->send();

                    return;
                }

                if ($consultation === null) {
                    $this->pendingAmdDocumentIds[] = $document->id;
                }

                Notification::make()
                    ->title('Archivo AMD guardado')
                    ->body($consultation
                        ? 'El archivo ya está disponible en los documentos del caso.'
                        : 'El archivo se vinculará al caso cuando guarde la consulta.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function informAmdFormDefaults(): array
    {
        $formState = $this->form->getState();

        return [
            'reason_consultation' => $formState['reason_consultation'] ?? null,
            'actual_phatology' => $formState['actual_phatology'] ?? null,
            'background' => $formState['background'] ?? null,
            'diagnostic_impression' => $formState['diagnostic_impression'] ?? null,
            'pa' => $formState['pa'] ?? null,
            'fc' => $formState['fc'] ?? null,
            'fr' => $formState['fr'] ?? null,
            'temp' => $formState['temp'] ?? null,
            'saturacion' => $formState['saturacion'] ?? null,
            'peso' => $formState['peso'] ?? null,
            'estatura' => $formState['estatura'] ?? null,
            'imc' => $formState['imc'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $formState
     * @return array<string, mixed>
     */
    protected function informAmdPendingContext(array $formState): array
    {
        $context = $formState;

        if (property_exists($this, 'case') && $this->case !== null) {
            $context['telemedicine_case_id'] = $this->case->id;
            $context['telemedicine_case_code'] = $this->case->code;
            $context['patient_phone'] = $this->case->patient_phone;
        }

        if (property_exists($this, 'patient') && $this->patient !== null) {
            $context['telemedicine_patient_id'] = $this->patient->id;
            $context['nro_identificacion'] ??= $this->patient->nro_identificacion;
            $context['full_name'] ??= $this->patient->full_name ?? $this->case?->patient_name;
            $context['age'] ??= $this->patient->age;
        }

        return $context;
    }

    protected function resolveConsultationForInformAmd(): ?TelemedicineConsultationPatient
    {
        if (method_exists($this, 'getRecord')) {
            $record = $this->getRecord();

            if ($record instanceof TelemedicineConsultationPatient && $record->exists) {
                return $record;
            }
        }

        return null;
    }
}
