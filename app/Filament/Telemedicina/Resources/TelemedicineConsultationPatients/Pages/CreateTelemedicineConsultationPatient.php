<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasInformAmdModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasMedicamentosStepInfoModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Http\Controllers\OperationCoordinationServiceController;
use App\Http\Controllers\TelemedicineMedicalReportController;
use App\Jobs\GeneratePdfEspecialista;
use App\Jobs\GeneratePdfImagenologia;
use App\Jobs\GeneratePdfInformeMedicoCorto;
use App\Jobs\GeneratePdfInformeMedicoLargo;
use App\Jobs\GeneratePdfLaboratorio;
use App\Jobs\GeneratePdfMedicamentos;
use App\Jobs\SendTelemedicineConsultationDocuments;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\User;
use App\Services\NotificationTelemedicinaService;
use App\Services\TelemedicineMedicationInventoryDeductor;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\ConsultationCreateWizardDefaults;
use App\Support\Telemedicine\TelemedicineAmdFileRegistrar;
use App\Support\Telemedicine\TelemedicineAmdInformRegistrar;
use App\Support\Telemedicine\TelemedicineCaseDischargeGuard;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicineInitialDiagnosisUpdater;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use App\Support\Telemedicine\TelemedicineMedicationsPdfRows;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateTelemedicineConsultationPatient extends CreateRecord
{
    use HasInformAmdModal;
    use HasMedicamentosStepInfoModal;

    protected static string $resource = TelemedicineConsultationPatientResource::class;

    /**
     * IDs persistidos por Livewire entre requests (las props protegidas no sobreviven al submit).
     */
    #[Locked]
    public ?int $telemedicineCaseId = null;

    #[Locked]
    public ?int $telemedicinePatientId = null;

    protected ?TelemedicinePatient $patient = null;

    protected ?TelemedicineCase $case = null;

    public function mount(): void
    {
        // 1. Obtener paciente y caso desde la sesión antes de inicializar el formulario.
        $this->patient = session()->get('patient');
        $this->case = session()->get('case');

        if (! $this->patient instanceof TelemedicinePatient || ! $this->case instanceof TelemedicineCase) {
            Notification::make()
                ->title('Error: información de sesión incompleta.')
                ->body('No se encontró el paciente o el caso para crear la consulta.')
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));

            return;
        }

        // Refrescar el caso y el paciente desde BD (evita sesión con identidad desfasada).
        $freshCase = TelemedicineCase::query()->find($this->case->id);
        if ($freshCase !== null) {
            $this->case = $freshCase;
            session(['case' => $freshCase]);
        }

        $freshPatient = TelemedicinePatient::query()->find($this->case->telemedicine_patient_id ?? $this->patient->id);
        if ($freshPatient === null) {
            Notification::make()
                ->title('Error: paciente no encontrado.')
                ->body('No se pudo refrescar el paciente vinculado al caso.')
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));

            return;
        }

        $this->patient = $freshPatient;
        session(['patient' => $freshPatient]);
        $this->rememberConsultationContextIds();

        // 2. Llama al mount original de Filament cuando la sesión está lista.
        parent::mount();

        if ($this->case !== null) {
            $countCase = TelemedicineConsultationPatient::query()
                ->where('telemedicine_case_id', $this->case->id)
                ->count();

            $formState = ConsultationCreateWizardDefaults::formStatePatientStepFromCaseAndPatient(
                $this->case,
                $this->patient,
                (int) Auth::user()->id,
                $countCase,
            );

            $lastConsultation = TelemedicineConsultationPatient::query()
                ->where('telemedicine_case_id', $this->case->id)
                ->orderByDesc('id')
                ->first();

            if ($lastConsultation !== null) {
                $formState = array_merge(
                    $formState,
                    ConsultationCreateWizardDefaults::formStatePrefillFromLastConsultation($lastConsultation),
                );
            }

            if ($countCase >= 1) {
                $formState = array_merge(
                    $formState,
                    TelemedicineInitialDiagnosisUpdater::formStateForCase((int) $this->case->id),
                );
            }

            $this->form->fill($formState);
        }

        // 3. Verificar si el paciente NO tiene historia clínica
        $hasHistory = TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $this->patient->id)
            ->exists();

        // Si NO tiene historia, muestra el modal (o redirige)
        if (! $hasHistory) {
            // Lógica para mostrar el modal:
            // Opción A: Usar una notificación/alerta clara con un enlace para crear la historia.

            // Creo la variable de session que va a manejar la respuesta
            session()->put('redCode', true);

            Notification::make()
                ->title('¡Atención: Historia Clínica Pendiente! ⚠️')
                ->body('El paciente no tiene un registro de historia clínica. Debe crearlo antes de continuar con la consulta. Si es un paciente crítico o de emergencia ingresa por clave roja.')
                ->actions([
                    // Este es el Action que se mostrará dentro de la Notificación
                    Action::make('create_history')
                        ->label('Historia Clínica')
                        ->button()
                        ->url(
                            TelemedicineHistoryPatientResource::getUrl('create', [
                                'patientId' => $this->patient->id, // Pasa el ID del paciente
                            ])
                        )
                        ->close(),
                    Action::make('halt')
                        ->label('Clave Roja')
                        ->icon('heroicon-c-finger-print')
                        ->color('critico')
                        ->button()
                        ->dispatch('undoEditingPost')
                        ->hidden(function () {
                            $consultation = TelemedicineConsultationPatient::where('telemedicine_case_id', $this->case->id)
                                ->where('telemedicine_case_code', $this->case->code)
                                ->where('status', 'CONSULTA INICIAL')
                                ->count();
                            if ($consultation > 0) {
                                return true;
                            }

                            return false;
                        })
                        ->close(),
                ])
                ->icon('heroicon-s-exclamation-triangle')
                ->iconColor('critico')
                ->color('critico') // Usa un color que llame la atención
                ->persistent() // Mantiene la notificación hasta que se cierre o actúe
                ->send();

            // Opcional: Redirigir inmediatamente a la creación de la historia para forzar el flujo.
            // Esto elimina la necesidad de que el usuario haga clic en el botón de la notificación.
            // return $this->redirect(\App\Filament\Telemedicina\Resources\TelemedicineHistoryPatientResource::getUrl('create', ['patientId' => $this->patient->id]));
        }

        // Si SÍ tiene historia clínica, el mount() continúa y carga el formulario normalmente.
    }

    /**
     * Define los eventos de Livewire que este componente debe escuchar.
     */
    protected function getListeners(): array
    {
        return [
            // 'evento' => 'metodo_a_ejecutar'
            'undoEditingPost' => 'handleUndoEditingPost',
            'open-medicamentos-step-info-modal' => 'openMedicamentosStepInfoModal',
        ];
    }

    /**
     * Método que se ejecuta cuando se dispara el evento 'undoEditingPost'.
     *
     * @param  array  $params  (Opcional: recibe los datos pasados por el evento)
     */
    public function handleUndoEditingPost(): void
    {

        // EJEMPLO: Resetear el formulario
        session()->put('redCode', false);

        // EJEMPLO: Mostrar una notificación
        Notification::make()
            ->title('¡Alerta de Clave Roja recibida! 🚨')
            ->body('El formulario ha recibido la señal de "Clave Roja".')
            ->icon('heroicon-c-finger-print')
            ->color('urgencia') // Usa un color que llame la atención()
            ->send();
    }

    public function getTitle(): string|Htmlable
    {

        $patient = session()->get('patient');
        if (! $patient instanceof TelemedicinePatient) {
            return 'Registrar consulta';
        }

        return new HtmlString(
            '<div style="display: flex; flex-direction: column;">'.
                '<span style="font-weight: bold; font-size: 1rem; color: #005ca9;">'. // Tono azul oscuro similar a primary-700
                    'Nombra y Apellido: '.$patient->full_name.
                '</span>'.
                '<span style="font-size: 1rem; color: #005ca9;">'. // Tono gris oscuro similar a gray-600
                    'Cédula: V-'.$patient->nro_identificacion.
                '</span>'.
                '<span style="font-size: 1rem; color: #005ca9;">'. // Tono gris oscuro similar a gray-600
                    'Edad: '.$patient->age.
                '</span>'.
            '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [

            Action::make('back_dashboard')
                ->label('Dashboard')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('estandar')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('estandar'),
                ])
                ->url(route('filament.telemedicina.pages.dashboard')),

            Action::make('create_history')
                ->label('Registrar Historia Clínica')
                ->button()
                ->slideOver()
                ->icon('healthicons-f-health-worker-form')
                ->color('urgencia')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('urgencia'),
                ])
                ->action(function () {

                    $patient = session()->get('patient');
                    $record = $patient?->telemedicinePatientHistory()->first();

                    return redirect(TelemedicineHistoryPatientResource::getUrl('create', ['record' => $patient->id]));
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicinePatientHistory()->exists();

                    return $records;
                }),

            Action::make('edit_history')
                ->label('Editar Historia Clínica')
                ->button()
                ->slideOver()
                ->icon('healthicons-f-health-worker-form')
                ->color('urgencia')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('urgencia'),
                ])
                ->action(function () {

                    $patient = session()->get('patient');
                    $record = $patient?->telemedicinePatientHistory()->first();
                    // dd($record);

                    return redirect(TelemedicineHistoryPatientResource::getUrl('edit', ['record' => $record->id]));

                    // return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.edit', ['id' => $records->id]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $record = $patient?->telemedicinePatientHistory()->exists();

                    return ! $record;
                }),

            Action::make('view_history')
                ->label('Resumen Historia Clínica')
                ->button()
                ->slideOver()
                ->icon('healthicons-f-health-worker-form')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->modalSubmitAction(false)
                ->modalContent(function () {

                    $patient = session()->get('patient');
                    $records = $patient?->telemedicinePatientHistory()->first();

                    return view('history-patient-infolist', ['record' => $records]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicinePatientHistory()->exists();

                    return ! $records;
                }),

            Action::make('consultation_history')
                ->label('Histórico del Caso')
                ->button()
                ->icon('heroicon-s-clipboard-document-list')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->slideOver()
                ->modalHeading('Historial de Casos del Paciente')
                ->modalContent(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicineConsultationPatients()->orderByDesc('created_at')->get();

                    // dd($records);
                    return view('consultation-patient-table', ['records' => $records]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient->telemedicineConsultationPatients()->exists();

                    return ! $records;
                }),

            Action::make('consultation_history_case')
                ->label('Últimos Casos')
                ->button()
                ->icon('heroicon-s-clipboard-document-list')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->slideOver()
                ->modalHeading('Historial de Casos del Paciente')
                ->modalContent(function () {
                    $patient = session()->get('patient');
                    $records = $patient?->telemedicineCases()->orderByDesc('created_at')->get();

                    // dd($records);
                    return view('table-telemedicine-cases', ['records' => $records]);
                })
                ->hidden(function () {
                    $patient = session()->get('patient');
                    $records = $patient->telemedicineCases()->exists();

                    return ! $records;
                }),

        ];
    }

    protected function getTelemedicineCaseTable() {}

    protected function getFormActions(): array
    {
        return [];
    }

    public function hydrate(): void
    {
        $this->resolveConsultationContext();
    }

    protected function rememberConsultationContextIds(): void
    {
        $this->telemedicineCaseId = $this->case instanceof TelemedicineCase
            ? (int) $this->case->id
            : null;
        $this->telemedicinePatientId = $this->patient instanceof TelemedicinePatient
            ? (int) $this->patient->id
            : null;
    }

    /**
     * Rehidrata caso/paciente en cada request Livewire (props protected no persisten).
     */
    protected function resolveConsultationContext(): void
    {
        $caseId = $this->telemedicineCaseId;
        if ($caseId === null) {
            $sessionCase = session()->get('case');
            if ($sessionCase instanceof TelemedicineCase) {
                $caseId = (int) $sessionCase->id;
            } elseif (is_object($sessionCase) && isset($sessionCase->id)) {
                $caseId = (int) $sessionCase->id;
            }
        }

        if ($caseId !== null && $caseId > 0) {
            $this->case = TelemedicineCase::query()->find($caseId);
            if ($this->case instanceof TelemedicineCase) {
                $this->telemedicineCaseId = (int) $this->case->id;
                session(['case' => $this->case]);
            }
        }

        $patientId = $this->telemedicinePatientId
            ?? (int) ($this->case?->telemedicine_patient_id ?? 0);

        if ($patientId < 1) {
            $sessionPatient = session()->get('patient');
            if ($sessionPatient instanceof TelemedicinePatient) {
                $patientId = (int) $sessionPatient->id;
            } elseif (is_object($sessionPatient) && isset($sessionPatient->id)) {
                $patientId = (int) $sessionPatient->id;
            }
        }

        if ($patientId > 0) {
            $this->patient = TelemedicinePatient::query()->find($patientId);
            if ($this->patient instanceof TelemedicinePatient) {
                $this->telemedicinePatientId = (int) $this->patient->id;
                session(['patient' => $this->patient]);
            }
        }
    }

    protected function failConsultationIdentity(string $message): never
    {
        Notification::make()
            ->title('No se pudo registrar la consulta')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            'data.telemedicine_patient_id' => [$message],
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->resolveConsultationContext();

        $casePatientId = (int) ($this->case?->telemedicine_patient_id ?? 0);
        $formPatientId = (int) ($data['telemedicine_patient_id'] ?? 0);
        $sessionPatientId = (int) ($this->patient?->id ?? 0);

        if ($casePatientId < 1) {
            $this->failConsultationIdentity('El caso no tiene un paciente vinculado. Vuelva a abrir la consulta desde el caso.');
        }

        if (($formPatientId > 0 && $formPatientId !== $casePatientId)
            || ($sessionPatientId > 0 && $sessionPatientId !== $casePatientId)) {
            $this->failConsultationIdentity('La identidad de la sesión no coincide con el paciente del caso. Vuelva a abrir la consulta desde el caso.');
        }

        $patient = TelemedicinePatient::query()->find($casePatientId);

        if ($patient === null) {
            $this->failConsultationIdentity('No se encontró el paciente vinculado al caso. Vuelva a abrir la consulta desde el caso.');
        }

        $this->patient = $patient;
        session(['patient' => $patient]);
        $this->rememberConsultationContextIds();
        $data = TelemedicinePatientIdentity::enforceConsultationIdentity($data, $patient);

        if (isset($data['feedbackOne']) && $data['feedbackOne'] == true) {
            $caseId = (int) ($data['telemedicine_case_id'] ?? 0);
            TelemedicineCaseDischargeGuard::assertCanBeDischarged($caseId);

            session()->put('feedbackOne', $data['feedbackOne']);
            $consult = TelemedicineConsultationPatient::where('telemedicine_case_id', $data['telemedicine_case_id'])->latest()->first();
            $data['telemedicine_service_list_id'] = $consult->telemedicine_service_list_drift_id;
        }
        // ...Asignamos los valores a la variable de sesion
        // Medicamentos
        isset($data['medications']) ? session()->put('medications', $data['medications']) : null;

        // Laboratorios
        isset($data['labs']) ? session()->put('labs', $data['labs']) : null;
        isset($data['other_labs']) ? session()->put('other_labs', $data['other_labs']) : null;

        // Estudios
        isset($data['studies']) ? session()->put('studies', $data['studies']) : null;
        isset($data['other_studies']) ? session()->put('other_studies', $data['other_studies']) : null;

        // Consultas con especialistas
        isset($data['consult_specialist']) ? session()->put('consult_specialist', $data['consult_specialist']) : null;
        isset($data['other_specialist']) ? session()->put('other_specialist', $data['other_specialist']) : null;

        return TelemedicineInitialDiagnosisUpdater::mergeIntoConsultationFormData($data);
    }

    /**
     * Creamos el registro de los medicamentos
     * asignados por el medico en la consulta
     *
     * @author TuDrEnCasa
     *
     * @since 1.0
     *
     * @version 1.0
     *
     * @param  array  $data,  array $medications
     * @return void
     */
    protected function afterCreate()
    {
        try {

            $record = $this->getRecord()->toArray();

            if (($record['status'] ?? '') !== TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS) {
                try {
                    TelemedicineInitialDiagnosisUpdater::syncFromFollowUp(
                        (int) ($record['telemedicine_case_id'] ?? 0),
                        (string) ($record['diagnostic_impression'] ?? $this->data[TelemedicineInitialDiagnosisUpdater::FORM_FIELD] ?? ''),
                        Auth::user() instanceof User ? Auth::user() : null,
                        isset($record['code_reference']) ? (string) $record['code_reference'] : null,
                    );
                } catch (\Throwable $diagnosisException) {
                    Log::error('Error al actualizar el diagnóstico principal de la consulta inicial: '.$diagnosisException->getMessage(), [
                        'telemedicine_case_id' => $record['telemedicine_case_id'] ?? null,
                        'telemedicine_consultation_id' => $record['id'] ?? null,
                        'exception' => $diagnosisException,
                    ]);

                    Notification::make()
                        ->title('No se pudo actualizar el diagnóstico principal')
                        ->body('La consulta de seguimiento se guardó, pero el diagnóstico de la consulta inicial no se actualizó. Revise la bitácora e intente de nuevo.')
                        ->danger()
                        ->send();
                }
            }

            if ((int) ($record['telemedicine_service_list_id'] ?? 0) === TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID) {
                $consultation = TelemedicineConsultationPatient::query()->find($record['id']);

                if ($consultation) {
                    TelemedicineAmdInformRegistrar::attachPendingToConsultation(
                        $consultation,
                        session()->get(TelemedicineAmdInformRegistrar::SESSION_PENDING_INFORM_ID)
                            ?? $this->pendingAmdInformId,
                    );

                    TelemedicineAmdFileRegistrar::attachPendingToConsultation($consultation);
                }
            }

            $doctor = TelemedicineDoctor::where('id', $record['telemedicine_doctor_id'])->first()->toArray();

            $patient = TelemedicinePatient::where('id', $record['telemedicine_patient_id'])->first()->toArray();

            // Envuelve el codigo en un try catch y una transaccion para que si hay un error se pueda revertir el cambio
            DB::transaction(function () use ($record, $doctor, $patient) {

                try {

                    // LIsta de Variables para generar los reportes
                    $dataMedicamentos = [];
                    $dataLaboratorios = [];
                    $dataEstudios = [];
                    $dataEspecialistas = [];

                    $feedbackOne = session()->get('feedbackOne');

                    $medicationsArr = TelemedicineMedicationsPdfRows::normalize(session()->get('medications') ?? []);
                    // dd($medicationsArr);
                    $labsArr = session()->get('labs') ?? [];
                    $otherLabsArr = session()->get('other_labs') ?? [];
                    $studiesArr = session()->get('studies') ?? [];
                    $otherStudiesArr = session()->get('other_studies') ?? [];
                    $consultSpecialistArr = session()->get('consult_specialist') ?? [];
                    $otherSpecialistArr = session()->get('other_specialist') ?? [];

                    if ($feedbackOne != true) {
                        $finalArrLabs = array_merge($labsArr, $otherLabsArr);
                        $finalArrStudies = array_merge($studiesArr, $otherStudiesArr);
                        $finalArrSpecialist = array_merge($consultSpecialistArr, $otherSpecialistArr);
                    }

                    // dd($finalArrLabs, $finalArrStudies, $finalArrSpecialist);

                    // Arreglo de medicamento
                    // if (! empty($medicationsArr) && $medicationsArr[0]['medicines'] != null || $medicationsArr[0]['operation_inventory_id'] != null) {
                    if (! empty($medicationsArr)) {
                        // dd($medicationsArr);
                        $caseForInventory = TelemedicineCase::query()
                            ->with('telemedicineDoctor')
                            ->find($record['telemedicine_case_id']);
                        $doctorModel = TelemedicineDoctor::query()->find($record['telemedicine_doctor_id']);
                        $patientModel = TelemedicinePatient::query()->find($record['telemedicine_patient_id']);
                        $consultationModel = TelemedicineConsultationPatient::query()->find($record['id']);
                        $inventoryDeductor = app(TelemedicineMedicationInventoryDeductor::class);

                        for ($i = 0; $i < count($medicationsArr); $i++) {
                            if (! is_array($medicationsArr[$i] ?? null)) {
                                continue;
                            }

                            $payload = TelemedicineMedicationCoverage::persistPayloadFromRow($medicationsArr[$i]);
                            if ($payload === null) {
                                continue;
                            }

                            $inventoryId = $payload['operation_inventory_id'];
                            $medications = new TelemedicinePatientMedications;
                            $medications->telemedicine_consultation_patient_id = $record['id'];
                            $medications->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $medications->telemedicine_case_id = $record['telemedicine_case_id'];
                            $medications->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $medications->medicine = $payload['medicine'];
                            $medications->indications = $medicationsArr[$i]['indications'];
                            $medications->duration = $medicationsArr[$i]['duration'];
                            $medications->quantity = TelemedicineMedicationsPdfRows::quantityFromRow($medicationsArr[$i]);
                            $medications->telemedicine_priority_id = $record['telemedicine_priority_id'];
                            $medications->operation_inventory_id = $inventoryId;
                            $medications->is_covered = $payload['is_covered'];
                            $medications->assigned_by = Auth::user()->id;
                            $medications->save();

                            if ($consultationModel !== null && $payload['should_deduct_inventory'] && $inventoryId !== null) {
                                $inventoryDeductor->deductIfApplicable(
                                    $inventoryId,
                                    $consultationModel,
                                    $caseForInventory,
                                    $doctorModel,
                                    $patientModel,
                                    TelemedicineMedicationsPdfRows::quantityForInventoryDeduction($medicationsArr[$i]),
                                );
                            }
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'medicamentos';

                        $dataMedicamentos = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'medicationsArr' => $medicationsArr,
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];

                        // GeneratePdfMedicamentos::dispatch($dataMedicamentos, Auth::user(), $typeDoc)->onQueue('telemedicina');
                    }

                    // Arreglo de Laboratorios
                    if (! empty($finalArrLabs)) {
                        // Log::info('Lab: ' . json_encode($medicationsArr));
                        for ($i = 0; $i < count($finalArrLabs); $i++) {
                            $labs = new TelemedicinePatientLab;
                            $labs->telemedicine_consultation_patient_id = $record['id'];
                            $labs->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $labs->telemedicine_case_id = $record['telemedicine_case_id'];
                            $labs->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $labs->laboratory = $finalArrLabs[$i];
                            $labs->type = TelemedicineListLaboratory::where('name', $finalArrLabs[$i])->first()->type;
                            $labs->assigned_by = Auth::user()->id;
                            $labs->save();
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'laboratorios';

                        $dataLaboratorios = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'labs' => $record['labs'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];

                        // GeneratePdfLaboratorio::dispatch($dataLaboratorios, Auth::user(), $typeDoc)->onQueue('telemedicina');
                    }

                    // Arreglo de Estudios
                    if (! empty($finalArrStudies)) {
                        // Log::info('Estudios: ' . json_encode($medicationsArr));
                        for ($i = 0; $i < count($finalArrStudies); $i++) {
                            $study = new TelemedicinePatientStudy;
                            $study->telemedicine_consultation_patient_id = $record['id'];
                            $study->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $study->telemedicine_case_id = $record['telemedicine_case_id'];
                            $study->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $study->study = $finalArrStudies[$i];
                            $study->assigned_by = Auth::user()->id;
                            $study->type = TelemedicineListStudy::where('name', $finalArrStudies[$i])->first()->type;
                            $study->save();
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'imagenologia';

                        $dataEstudios = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'studies' => $record['studies'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'phone' => $patient['phone'],
                            'signature' => $doctor['signature'],
                        ];

                        // Bus::chain([

                        //     new GeneratePdfImagenologia($data, Auth::user(), $typeDoc),

                        //     new SendTelemedicinaDocument($data['telemedicine_patient_id'], $data['telemedicine_case_id'], Auth::user(), $patient['phone'], $typeDoc),

                        // ])->onQueue('telemedicina')->dispatch();

                        // GeneratePdfImagenologia::dispatch($dataEstudios, Auth::user(), $typeDoc)->onQueue('telemedicina');

                    }

                    // Arreglo Especialistas
                    if (! empty($finalArrSpecialist)) {
                        // Log::info('Especialista: ' . json_encode($medicationsArr));
                        for ($i = 0; $i < count($finalArrSpecialist); $i++) {
                            $specialist = new TelemedicinePatientSpecialty;
                            $specialist->telemedicine_consultation_patient_id = $record['id'];
                            $specialist->telemedicine_patient_id = $record['telemedicine_patient_id'];
                            $specialist->telemedicine_case_id = $record['telemedicine_case_id'];
                            $specialist->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                            $specialist->specialty = $finalArrSpecialist[$i];
                            $specialist->assigned_by = Auth::user()->id;
                            $specialist->type = TelemedicineListSpecialist::where('name', $finalArrSpecialist[$i])->first()->type;
                            $specialist->save();
                        }

                        /**
                         * Informacion para el pdf
                         * -------------------------------------------------------------------------------------------
                         *
                         * @typeDoc = Tipo de documento a generar
                         *
                         * @doctor = Informacion del doctor
                         *
                         * @recod = Informacion de la consulta
                         */
                        $typeDoc = 'especialista';

                        $dataEspecialistas = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $record['code_reference'],
                            'name_patiente' => $record['full_name'],
                            'ci_patiente' => $record['nro_identificacion'],
                            'age_patiente' => $patient['age'],
                            'consultSpecialistArr' => $consultSpecialistArr,
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];
                        // dd($data);

                        // GeneratePdfEspecialista::dispatch($dataEspecialistas, Auth::user(), $typeDoc)->onQueue('telemedicina');
                    }

                    // ...Limpio la variable de sesion
                    session()->forget('medications');
                    session()->forget('labs');
                    session()->forget('other_labs');
                    session()->forget('studies');
                    session()->forget('other_studies');
                    session()->forget('consult_specialist');
                    session()->forget('other_specialist');

                    // ...Activacion de la clave roja
                    session()->forget('redCode');

                    // ...Limpio la variable de sesion que se generar al momento acceder al caso para la primera consulta
                    session()->forget('case');
                    session()->forget('patient');
                    session()->forget('redCode');

                    // ...Limpio la variable de sesion que se crea cuando asociamos algun antecedente de la lista
                    session()->forget('patologicalHistorySelected');

                    // Actualizo el estatus del

                    if (isset($feedbackOne) && $feedbackOne == true) {
                        $caseId = (int) $record['telemedicine_case_id'];

                        if (! TelemedicineCaseDischargeGuard::caseCanBeDischarged($caseId)) {
                            Notification::make()
                                ->title('Alta médica bloqueada')
                                ->body(TelemedicineCaseDischargeGuard::blockingMessage($caseId))
                                ->danger()
                                ->send();

                            $case = TelemedicineCase::where('id', $caseId)->first();
                            $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                            $case->updated_at = now();
                            $case->status = 'EN SEGUIMIENTO';
                            $case->save();

                            session()->forget('feedbackOne');
                        } else {
                            // Actualizamos la informacion en la tabla de casos
                            $case = TelemedicineCase::where('id', $caseId)->first();
                            $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                            $case->updated_at = now();
                            $case->status = 'ALTA MEDICA';
                            $case->save();

                            // Actualizamos la informacion en la tabla de consultas
                            $consult = TelemedicineConsultationPatient::where('telemedicine_case_id', $caseId)->latest()->first();
                            $consult->updated_at = now();
                            $consult->status = 'ALTA MEDICA';
                            $consult->save();

                            session()->forget('feedbackOne');
                        }

                    } else {
                        $case = TelemedicineCase::where('id', $record['telemedicine_case_id'])->first();
                        $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                        $case->updated_at = now();
                        $case->status = 'EN SEGUIMIENTO';
                        $case->save();
                    }

                    // Notificion al usuario de que los documentos estan siendo generados y qye luego los recibira via WP
                    $this->sendNotifications();

                    $pdfJobs = [];

                    if ($record['status'] == 'CONSULTA INICIAL') {

                        $dataInformeCorteo = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $this->data['code_reference'],
                            'name_patient' => $this->data['full_name'],
                            'ci_patient' => $this->data['nro_identificacion'],
                            'age_patient' => $this->data['age'],
                            'reason' => $this->data['reason_consultation'],
                            'actual_phatology' => $this->data['actual_phatology'],
                            'background' => $this->data['background'],
                            'diagnostic_impression' => $this->data['diagnostic_impression'],
                            'peso' => $this->data['peso'],
                            'estatura' => $this->data['estatura'],
                            'imc' => $this->data['imc'],
                            'phone' => $this->data['phone_ppal'],
                            'consultSpecialistArr' => $consultSpecialistArr,
                            'medicationsArr' => $medicationsArr ?? [],
                            'labsArr' => $labsArr ?? [],
                            'otherLabsArr' => $otherLabsArr ?? [],
                            'studiesArr' => $studiesArr ?? [],
                            'otherStudiesArr' => $otherStudiesArr ?? [],
                            'consultSpecialistArr' => $consultSpecialistArr ?? [],
                            'otherSpecialistArr' => $otherSpecialistArr ?? [],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                        ];

                        $pdfJobs[] = new GeneratePdfInformeMedicoCorto($dataInformeCorteo, Auth::user(), 'informe-corto');

                        $dataInformeLargo = [
                            'fecha' => now()->format('d/m/Y'),
                            'code_reference' => $this->data['code_reference'],
                            'name_patient' => $this->data['full_name'],
                            'ci_patient' => $this->data['nro_identificacion'],
                            'age_patient' => $this->data['age'],
                            'reason' => $this->data['reason_consultation'],
                            'actual_phatology' => $this->data['actual_phatology'],
                            'background' => $this->data['background'],
                            'diagnostic_impression' => $this->data['diagnostic_impression'],
                            'peso' => $this->data['peso'],
                            'estatura' => $this->data['estatura'],
                            'imc' => $this->data['imc'],
                            'phone' => $this->data['phone_ppal'],
                            'consultSpecialistArr' => $consultSpecialistArr,
                            'medicationsArr' => $medicationsArr ?? [],
                            'labsArr' => $labsArr ?? [],
                            'otherLabsArr' => $otherLabsArr ?? [],
                            'studiesArr' => $studiesArr ?? [],
                            'otherStudiesArr' => $otherStudiesArr ?? [],
                            'consultSpecialistArr' => $consultSpecialistArr ?? [],
                            'otherSpecialistArr' => $otherSpecialistArr ?? [],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'code_cm' => $doctor['code_cm'],
                            'code_mpps' => $doctor['code_mpps'],
                            'signature' => $doctor['signature'],
                            'telemedicine_case_id' => $record['telemedicine_case_id'],
                            'telemedicine_consultation_id' => $record['id'],
                            'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                            'signature' => $doctor['signature'],
                            'pa' => $this->data['pa'],
                            'fc' => $this->data['fc'],
                            'fr' => $this->data['fr'],
                            'temp' => $this->data['temp'],
                            'saturacion' => $this->data['saturacion'],
                        ];

                        $isAmdService = (int) ($record['telemedicine_service_list_id'] ?? 0) === TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID;

                        if (! $isAmdService) {
                            $pdfJobs[] = new GeneratePdfInformeMedicoLargo($dataInformeLargo, Auth::user(), 'informe-largo');
                        }
                    }

                    /**
                     * Ejecucion de Jobs para crear los documentos PDF
                     * ----------------------------------------------------------------------------------------------------
                     *
                     * $dataMedicamentos type array
                     * $dataLaboratorios type array
                     * $dataEstudios type array
                     * $dataEspecialistas type array
                     */
                    if ($dataMedicamentos != []) {

                        $pdfJobs[] = new GeneratePdfMedicamentos($dataMedicamentos, Auth::user(), 'medicamentos');

                        // Genero el servicio de coordinacion
                        $registeredOperationCoordinationService = OperationCoordinationServiceController::createMedicineService($record, $doctor, $patient);

                    }

                    if ($dataLaboratorios != []) {

                        $pdfJobs[] = new GeneratePdfLaboratorio($dataLaboratorios, Auth::user(), 'laboratorios');

                        $registeredOperationCoordinationService = OperationCoordinationServiceController::createLaboratoryService($record, $doctor, $patient);

                    }

                    if ($dataEstudios != []) {

                        $pdfJobs[] = new GeneratePdfImagenologia($dataEstudios, Auth::user(), 'imagenologia');

                        $registeredOperationCoordinationService = OperationCoordinationServiceController::createStudyService($record, $doctor, $patient);

                    }

                    if ($dataEspecialistas != []) {

                        $pdfJobs[] = new GeneratePdfEspecialista($dataEspecialistas, Auth::user(), 'especialista');

                        $registeredOperationCoordinationService = OperationCoordinationServiceController::createSpecialistService($record, $doctor, $patient);

                    }

                    if ($pdfJobs !== []) {
                        $consultationId = (int) $record['id'];
                        $userId = (int) Auth::id();
                        $patientPhone = (string) ($patient['phone'] ?? $this->data['phone_ppal'] ?? '');
                        $patientEmail = (string) ($patient['email'] ?? $patient['email_contact'] ?? '');
                        $patientName = (string) ($patient['full_name'] ?? $record['full_name']);

                        Bus::batch($pdfJobs)
                            ->name('telemedicina-consultation-docs-'.$consultationId)
                            ->then(function () use ($consultationId, $patientPhone, $patientEmail, $patientName, $userId): void {
                                SendTelemedicineConsultationDocuments::dispatch(
                                    $consultationId,
                                    $patientPhone,
                                    $patientEmail !== '' ? $patientEmail : null,
                                    $patientName,
                                    $userId,
                                )->onQueue('telemedicina');
                            })
                            ->onQueue('telemedicina')
                            ->dispatch();
                    }

                    /**
                     * Creacion del servicio de coordinacion
                     * ----------------------------------------------------------------------------------------------------
                     *
                     * @record = Informacion de la consulta
                     *
                     * @doctor = Informacion del doctor
                     *
                     * @patient = Informacion del paciente
                     *
                     * * @version 1.0
                     */
                    $serviceListDriftId = (int) (
                        $record['telemedicine_service_list_drift_id']
                        ?? $this->data['telemedicine_service_list_drift_id']
                        ?? 0
                    );

                    /** TRASLADO EN AMBULANCIA */
                    if ($serviceListDriftId === 3) {
                        $registeredOperationCoordinationService = OperationCoordinationServiceController::createServiceTransportAmbulance($record, $doctor, $patient);
                    }

                    /** INGRESO A CLINICA */
                    if ($serviceListDriftId === 8) {
                        $registeredOperationCoordinationService = OperationCoordinationServiceController::createServiceEnterClinic($record, $doctor, $patient);
                    }

                    /**
                     * Creacion del informe medico
                     * ----------------------------------------------------------------------------------------------------
                     *
                     * @record = Informacion de la consulta
                     *
                     * * @version 1.0
                     */
                    TelemedicineMedicalReportController::create($record);

                    Notification::make()
                        ->title('Telemedicina creada exitosamente')
                        ->success()
                        ->send();

                } catch (\Throwable $th) {
                    Log::error('Error al crear la telemedicina: '.$th->getMessage());
                    Notification::make()
                        ->title('Error al crear la telemedicina')
                        ->body($th->getMessage())
                        ->danger()
                        ->send();
                    throw $th;
                }
            });

            $registeredOperationCoordinationService = OperationCoordinationService::query()
                ->where('telemedicine_consultation_patient_id', $record['id'])
                ->where('status', 'PENDIENTE')
                ->latest()
                ->get();

            if ($registeredOperationCoordinationService->isNotEmpty()) {
                foreach ($registeredOperationCoordinationService as $operationCoordinationService) {
                    if ($operationCoordinationService->specific_service == 'MEDICAMENTOS') {
                        $medications = TelemedicinePatientMedications::query()
                            ->where('telemedicine_consultation_patient_id', $record['id'])
                            ->latest()
                            ->get();
                        foreach ($medications as $medication) {
                            $medication->operation_coordination_service_id = $operationCoordinationService->id;
                            $medication->save();
                        }
                    }
                    if ($operationCoordinationService->specific_service == 'LABORATORIOS') {
                        $labs = TelemedicinePatientLab::query()
                            ->where('telemedicine_consultation_patient_id', $record['id'])
                            ->latest()
                            ->get();
                        foreach ($labs as $lab) {
                            $lab->operation_coordination_service_id = $operationCoordinationService->id;
                            $lab->save();
                        }
                    }
                    if ($operationCoordinationService->specific_service == 'IMAGENOLOGIA') {
                        $studies = TelemedicinePatientStudy::query()
                            ->where('telemedicine_consultation_patient_id', $record['id'])
                            ->latest()
                            ->get();
                        foreach ($studies as $study) {
                            $study->operation_coordination_service_id = $operationCoordinationService->id;
                            $study->save();
                        }
                    }
                    if ($operationCoordinationService->specific_service == 'ESPECIALISTA') {
                        $specialists = TelemedicinePatientSpecialty::query()
                            ->where('telemedicine_consultation_patient_id', $record['id'])
                            ->latest()
                            ->get();
                        foreach ($specialists as $specialist) {
                            $specialist->operation_coordination_service_id = $operationCoordinationService->id;
                            $specialist->save();
                        }
                    }
                }
            }

            session()->forget('consultation');

        } catch (\Throwable $th) {
            Log::error('Error en afterCreate (telemedicina): '.$th->getMessage(), [
                'exception' => $th,
            ]);
            Notification::make()
                ->title('Error al crear la consulta de telemedicina')
                ->body($th->getMessage())
                ->danger()
                ->send();
            throw $th;
        }

    }

    private function sendNotifications()
    {
        $record = $this->getRecord()->toArray();

        $patient = TelemedicinePatient::where('id', $record['telemedicine_patient_id'])->first()->toArray();

        $masiveNotification = new NotificationTelemedicinaService;
        $masiveNotification->sendPreviewNotification($patient['phone']);
    }

    public function getRedirectUrl(): string
    {
        // redirect to dashboard
        return URL::route('filament.telemedicina.pages.dashboard');
    }
}
