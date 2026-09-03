<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use App\Enums\ClinicalServiceChannel;
use App\Models\NoPathologicalHistory;
use App\Models\OperationInventory;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineGeneralService;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePriority;
use App\Models\TelemedicineServiceList;
use App\Support\ClinicalEntitlements\ClinicalQuotaFormGuard;
use App\Support\ClinicalEntitlements\TelemedicineConsultationClinicalUi;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineCaseDischargeGuard;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicineInitialDiagnosisUpdater;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use App\Support\Telemedicine\TelemedicineMedicationInventoryOptions;
use App\Support\Telemedicine\TelemedicineSupplyInventoryOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\LivewireField;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\GridDirection;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class TelemedicineConsultationPatientForm
{
    private const WIZARD_IOS_CLASS = 'fi-telemedicine-consultation-wizard';

    private static function caseWithDoctor(mixed $case): ?TelemedicineCase
    {
        if (! $case instanceof TelemedicineCase) {
            return null;
        }

        if (! $case->relationLoaded('telemedicineDoctor')) {
            $case->loadMissing('telemedicineDoctor');
        }

        return $case;
    }

    /**
     * Insumos médicos consumidos por el médico en la consulta o el seguimiento.
     *
     * El listado sale del inventario (categoría de producto «Insumos Médicos») y,
     * cuando el consumo descuenta existencias, se acota al almacén del caso.
     */
    private static function medicalSuppliesFieldset(mixed $case): Fieldset
    {
        return Fieldset::make('Insumos médicos consumidos')
            ->schema([
                Placeholder::make('medical_supplies_empty_notice')
                    ->hiddenLabel()
                    ->content('Todavía no hay insumos médicos cargados en el inventario. En cuanto Operaciones los registre aparecerán aquí para seleccionarlos.')
                    ->visible(fn (): bool => self::supplyOptionsForCase($case) === [])
                    ->columnSpanFull(),
                Repeater::make('medical_supplies')
                    ->hiddenLabel()
                    ->addActionLabel('Agregar insumo consumido')
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->visible(fn (): bool => self::supplyOptionsForCase($case) !== [])
                    ->table([
                        TableColumn::make('Insumo médico'),
                        TableColumn::make('Cantidad consumida')->width('22%'),
                    ])
                    ->schema([
                        Select::make('operation_inventory_id')
                            ->label('Insumo médico')
                            ->placeholder('Busque el insumo por nombre')
                            ->options(fn (): array => self::supplyOptionsForCase($case))
                            ->getSearchResultsUsing(fn (string $search): array => TelemedicineSupplyInventoryOptions::searchOptionsForCase(
                                self::caseWithDoctor($case),
                                $search,
                                self::caseWithDoctor($case)?->telemedicineDoctor,
                            ))
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (! filled($value)) {
                                    return null;
                                }

                                $name = OperationInventory::query()->whereKey($value)->value('name');

                                return filled($name) ? (string) $name : null;
                            })
                            ->searchable()
                            ->preload()
                            ->distinct()
                            ->required()
                            ->validationMessages([
                                'required' => 'Seleccione el insumo médico consumido.',
                                'distinct' => 'Este insumo ya está en la lista: ajuste la cantidad en lugar de repetirlo.',
                            ]),
                        TextInput::make('quantity')
                            ->label('Cantidad consumida')
                            ->numeric()
                            ->integer()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->maxValue(fn (Get $get): int|float => TelemedicineSupplyInventoryOptions::availableExistence(
                                (int) $get('operation_inventory_id')
                            ) ?? INF)
                            ->validationMessages([
                                'required' => 'Indique cuántas unidades consumió.',
                                'min' => 'La cantidad debe ser al menos 1.',
                                'max' => 'La cantidad supera la existencia disponible del insumo.',
                            ]),
                    ])
                    ->columnSpanFull(),
            ])->columnSpanFull()->columns(1);
    }

    /**
     * Memorizado por request: el fieldset consulta las opciones para la
     * visibilidad del aviso, la del repetidor y el propio select.
     *
     * @var array<string, array<int|string, string>>
     */
    private static array $supplyOptionsCache = [];

    /**
     * @return array<int|string, string>
     */
    private static function supplyOptionsForCase(mixed $case): array
    {
        $caseModel = self::caseWithDoctor($case);
        $cacheKey = 'case:'.($caseModel?->id ?? 'none');

        return self::$supplyOptionsCache[$cacheKey] ??= TelemedicineSupplyInventoryOptions::optionsForCase(
            $caseModel,
            $caseModel?->telemedicineDoctor,
        );
    }

    private static function informAmdTrigger(): View
    {
        return View::make('filament.telemedicina.consultations.inform-amd-trigger')
            ->columnSpanFull()
            ->visible(fn (Get $get): bool => (int) $get('telemedicine_service_list_id') === TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID);
    }

    private static function isConsultaGeneralSelected(Get $get): bool
    {
        return (int) $get('telemedicine_service_list_id') === TelemedicineServiceList::CONSULTA_GENERAL_ID;
    }

    /**
     * El cuestionario de seguimiento aplica cuando el caso ya tiene consulta inicial
     * y no se está editando esa consulta inicial.
     */
    private static function isFollowUpConsultationContext(int $countCase): bool
    {
        $action = session()->get('action') ?? null;

        if ($countCase < 1) {
            return false;
        }

        if (isset($action) && $action == 'edit' && session()->get('status') == 'CONSULTA INICIAL') {
            return false;
        }

        return true;
    }

    private static function generalServiceSelect(): Select
    {
        return Select::make('telemedicine_general_service_id')
            ->label('Servicio General')
            ->live()
            ->options(fn (): \Illuminate\Support\Collection => TelemedicineGeneralService::query()
                ->active()
                ->orderBy('name')
                ->pluck('name', 'id'))
            ->searchable()
            ->preload()
            ->nullable()
            ->placeholder('Opcional — seleccione si aplica')
            ->visible(fn (Get $get): bool => self::isConsultaGeneralSelected($get))
            ->dehydrated(fn (Get $get): bool => self::isConsultaGeneralSelected($get))
            ->helperText('Opcional. Catálogo de Consulta General, gestionado por analistas TDG. Puede dejarlo vacío.');
    }

    private static function complementsCheckboxList(): CheckboxList
    {
        return CheckboxList::make('complements')
            ->label('Complementos')
            ->columnSpanFull(1)
            ->live()
            ->gridDirection(GridDirection::Row)
            ->options(fn (): array => TelemedicineConsultationClinicalUi::complementOptions())
            ->descriptions(fn (): array => TelemedicineConsultationClinicalUi::complementOptionDescriptions())
            ->rules([
                fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::complementsRule($livewire),
            ])
            ->helperText(function (Get $get, Component $livewire): ?string {
                $blocked = ClinicalQuotaFormGuard::helperText($livewire, ClinicalServiceChannel::Medication);

                if ($blocked !== null && in_array(1, array_map('intval', (array) $get('complements')), true)) {
                    return $blocked;
                }

                return TelemedicineConsultationClinicalUi::complementsHelperText($get('complements'));
            })
            ->hint(fn (Get $get): ?string => TelemedicineConsultationClinicalUi::specialistNotContemplatedHint($get('complements')))
            ->hintColor('warning')
            ->hintIcon(fn (Get $get): ?Heroicon => TelemedicineConsultationClinicalUi::specialistNotContemplatedHint($get('complements')) !== null
                ? Heroicon::OutlinedExclamationTriangle
                : null)
            ->afterStateUpdated(function (mixed $state, mixed $old, Component $livewire): void {
                $blocked = ClinicalQuotaFormGuard::blockedComplementChannel($livewire, $state);

                if ($blocked !== null && ! in_array(1, array_map('intval', (array) $old), true)) {
                    ClinicalQuotaFormGuard::notifyIfBlocked($livewire, ClinicalServiceChannel::Medication);
                }

                if (! TelemedicineConsultationClinicalUi::shouldNotifySpecialistNotContemplated($state, $old)) {
                    return;
                }

                Notification::make()
                    ->title('Especialista no contemplado en el uso clínico')
                    ->body(TelemedicineConsultationClinicalUi::SPECIALIST_NOT_CONTEMPLATED_MESSAGE)
                    ->warning()
                    ->send();
            });
    }

    /**
     * @param  mixed  $state
     */
    private static function syncServiceListSideEffects(Set $set, Get $get, $state): void
    {
        if ((string) $get('telemedicine_service_list_drift_id') === (string) $state
            && ! TelemedicineConsultationClinicalUi::isFollowUpServiceListId(filled($state) ? (int) $state : null)) {
            $set('telemedicine_service_list_drift_id', null);
        }

        if ((int) $state !== TelemedicineServiceList::CONSULTA_GENERAL_ID) {
            $set('telemedicine_general_service_id', null);
        }
    }

    public static function configure(Schema $schema): Schema
    {
        // Variables recuperadas de la sesion del usuario
        // ------------------------------------------------
        $case = session()->get('case');
        $patient = session()->get('patient');
        $consultation = session()->get('consultation');
        $caseId = $case?->id;
        $defaultTelemedicineServiceListId = null;
        if ($consultation instanceof TelemedicineConsultationPatient) {
            if (filled($consultation->telemedicine_service_list_drift_id)) {
                $defaultTelemedicineServiceListId = (int) $consultation->telemedicine_service_list_drift_id;
            } elseif (filled($consultation->telemedicine_service_list_id)) {
                $defaultTelemedicineServiceListId = (int) $consultation->telemedicine_service_list_id;
            }
        }
        $isTelemedicineServiceListIdLocked = $defaultTelemedicineServiceListId !== null;
        $countCase = filled($caseId)
            ? TelemedicineConsultationPatient::where('telemedicine_case_id', $caseId)->count()
            : 0;
        $caseCanBeDischarged = filled($caseId)
            ? TelemedicineCaseDischargeGuard::caseCanBeDischarged((int) $caseId)
            : true;
        $dischargeBlockedMessage = filled($caseId) && ! $caseCanBeDischarged
            ? TelemedicineCaseDischargeGuard::blockingMessage((int) $caseId)
            : null;

        return $schema
            ->components([
                Wizard::make([

                    Step::make('Datos del Paciente')
                        ->description('Verifica referencia, caso y datos de contacto del paciente.')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->schema([
                            Section::make()
                                ->heading('Datos del Paciente')
                                ->description('Información principal sobre el paciente')
                                ->icon(Heroicon::OutlinedIdentification)
                                ->iconColor('primary')
                                ->schema([
                                    Fieldset::make('Datos del Caso')
                                        ->schema([
                                            Hidden::make('telemedicine_case_id')->default($case->id),
                                            Hidden::make('telemedicine_doctor_id')->default($case->telemedicine_doctor_id),
                                            Hidden::make('telemedicine_patient_id')->default($case->telemedicine_patient_id),
                                            Hidden::make('assigned_by')->default(Auth::user()->id),
                                            Hidden::make('status')->default(function () use ($countCase) {
                                                if ($countCase < 1) {
                                                    return 'CONSULTA INICIAL';
                                                }

                                                return 'EN SEGUIMIENTO';
                                            }),
                                            TextInput::make('code_reference')
                                                ->label('Referencia')
                                                ->default('REF-'.rand(11111, 99999))
                                                ->required()
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('telemedicine_case_code')
                                                ->label('Código del Caso')
                                                ->default($case->code)
                                                ->disabled()
                                                ->dehydrated(),
                                        ])->columnSpanFull()->columns(6),

                                    Fieldset::make('Información Adicional')
                                        ->schema([
                                            TextInput::make('full_name')
                                                ->label('Paciente')
                                                ->default($patient->full_name)
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('nro_identificacion')
                                                ->label('Número de Identificación')
                                                ->prefix('V-')
                                                ->default($patient->nro_identificacion)
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('sex')
                                                ->label('Sexo')
                                                ->default($patient->sex)
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('age')
                                                ->label('Edad')
                                                ->prefix(' Años')
                                                ->default($patient->age)
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('phone_ppal')
                                                ->label('Número de Teléfono Principal')
                                                ->default($case->patient_phone)
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('phone_secondary')
                                                ->label('Número de Teléfono Secundario')
                                                ->default($case->patient_phone_2)
                                                ->disabled()
                                                ->dehydrated(),
                                            TextArea::make('address')
                                                ->autosize()
                                                ->label('Dirección')
                                                ->helperText('Direccion descrita por el paciente al momento de la asignación del caso.')
                                                ->default($case->patient_address)
                                                ->disabled()
                                                ->columnSpanFull()
                                                ->dehydrated(),
                                            TextArea::make('directionAmbulance')
                                                ->autosize()
                                                ->label('Dirección alternativa para estacionamiento de Ambulancia')
                                                ->helperText('Esta en la dirección alternativa donde el paciente puede recibir un servicio de ambulancia.')
                                                ->default($case->directionAmbulance)
                                                ->disabled()
                                                ->columnSpanFull()
                                                ->dehydrated()
                                                ->hidden(fn () => $case->directionAmbulance == null),
                                        ])->columnSpanFull()->columns(3),
                                ])
                                ->columnSpanFull(),

                            Section::make('Prioridad de servicio')
                                ->description('Aplica en consulta inicial y en seguimiento. Debe indicarse siempre, incluso si en un paso posterior se registra alta médica.')
                                ->icon(Heroicon::OutlinedBolt)
                                ->iconColor('warning')
                                ->schema([
                                    Select::make('telemedicine_priority_id')
                                        ->label('Prioridad de servicio')
                                        ->live()
                                        ->options(TelemedicinePriority::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->required(),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                    Step::make('Motivo de la Consulta')
                        ->description('Signos vitales, motivo de consulta y tipo de servicio.')
                        ->icon(Heroicon::OutlinedHeart)
                        ->hidden(function () use ($countCase) {

                            $action = session()->get('action') ?? null;

                            if ($countCase < 1) {
                                return false;
                            }
                            if (isset($action) && $action == 'edit' && session()->get('status') == 'CONSULTA INICIAL') {
                                return false;
                            }

                            return true;
                        })
                        ->schema([
                            Fieldset::make('Información sobre Signos Vitales')
                                ->schema([
                                    TextInput::make('pa')
                                        ->label('Presión Arterial')
                                        ->helperText('Presión Arterial (mmHg)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils'),
                                    TextInput::make('fc')
                                        ->label('Frecuencia Cardíaca')
                                        ->helperText('Frecuencia Cardíaca (lpm)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils'),
                                    TextInput::make('fr')
                                        ->label('Frecuencia Respiratoria')
                                        ->helperText('Frecuencia Respiratoria (rpm)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils'),
                                    TextInput::make('temp')
                                        ->label('Temperatura')
                                        ->helperText('Temperatura (°C)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils'),
                                    TextInput::make('saturacion')
                                        ->label('Saturación')
                                        ->helperText('Saturación (% de oxigeno en sangre)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils'),
                                ])->columnSpanFull()->columns(5),

                            Fieldset::make('Indice de Masa Corporal (IMC)')
                                ->schema([
                                    TextInput::make('peso')
                                        ->label('Peso')
                                        ->helperText('Peso (kg), el punto(.) es el separador de decimales. Ej: 60.5')
                                        ->numeric()
                                        ->live(onBlur: true)
                                        ->prefixIcon('healthicons-f-i-utensils'),
                                    TextInput::make('estatura')
                                        ->label('Estatura')
                                        ->helperText('Metros(mts), el punto(.) es el separador de decimales, Ej: 1.70')
                                        ->numeric()
                                        ->live(onBlur: true)
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->afterStateUpdated(function (string $context, $state, Set $set, Get $get) {
                                            $cal = $get('peso') / ($get('estatura') * $get('estatura'));
                                            $set('imc', round($cal, 2));
                                        }),
                                    TextInput::make('imc')
                                        // peso/estatura * 2
                                        ->label('Indice de Masa Corporal (IMC)')
                                        ->helperText('')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated()
                                        ->prefixIcon('healthicons-f-i-utensils'),
                                ])->columnSpanFull()->columns(3),

                            Fieldset::make('Consulta')
                                ->schema([
                                    Grid::make(1)
                                        ->schema([
                                            Textarea::make('reason_consultation')
                                                ->label('Motivo de Consulta')
                                                ->helperText('Precargado con el motivo indicado por el analista al asignar el caso. Puede ajustarlo si es necesario.')
                                                ->autosize()
                                                ->default(fn (): ?string => filled($case?->reason) ? (string) $case->reason : null)
                                                ->afterStateUpdatedJs(<<<'JS'
                                    $set('reason_consultation', $state.toUpperCase());
                                JS),
                                            Textarea::make('actual_phatology')
                                                ->label('Enfermedad Actual')
                                                ->autosize()
                                                ->afterStateUpdatedJs(<<<'JS'
                                        $set('actual_phatology', $state.toUpperCase());
                                    JS),

                                            Textarea::make('background')
                                                ->label('Antecedentes Asociados')
                                                ->autosize()
                                                ->default(function () {
                                                    $history = session()->get('patologicalHistorySelected');
                                                    Log::info($history);
                                                    if ($history) {
                                                        return $history;
                                                    }

                                                    return null;
                                                })
                                                ->belowContent([
                                                    // Icon::make(Heroicon::InformationCircle),
                                                    // 'This is the user\'s full name.',
                                                    Action::make('associatePathologicalHistory')
                                                        ->label('Asociar Antecedente')
                                                        ->color('no-urgente')
                                                        ->icon('heroicon-s-share')
                                                        ->slideOver()
                                                        ->modalHeading('Histórico de Antecedentes No Patológicos')
                                                        ->modalContent(function () {

                                                            $patient = session()->get('patient');
                                                            $records = $patient?->telemedicinePatientHistory()->orderByDesc('created_at')->get()->first();
                                                            $record = $records->toArray();
                                                            $history = NoPathologicalHistory::where('telemedicine_history_patient_id', $record['id'])->get();

                                                            return view('pathological-history-table', ['records' => $history]);
                                                        })
                                                        ->action(function (Action $action, Component $livewire) use ($case, $patient) { // 👈 INYECTA Component $livewire

                                                            // 1. **Lógica de procesamiento (Aquí se establece la sesión)**
                                                            // Si la modal tiene campos, se procesan aquí.
                                                            $nuevoValorDeSesion = session()->get('patologicalHistorySelected');
                                                            // Session::put('patologicalHistorySelected', $nuevoValorDeSesion);

                                                            // 2. **Sincronización del estado (EL PASO CLAVE)**
                                                            // Accede al formulario del componente Livewire y establece el valor del campo 'background'.
                                                            $livewire->form->fill([
                                                                'telemedicine_case_id' => $case->id,
                                                                'telemedicine_doctor_id' => $case->telemedicine_doctor_id,
                                                                'telemedicine_patient_id' => $case->telemedicine_patient_id,
                                                                'assigned_by' => Auth::user()->id,
                                                                'status' => 'CONSULTA INICIAL',
                                                                'code_reference' => 'REF-'.rand(11111, 99999),
                                                                'full_name' => $case->patient_name,
                                                                'telemedicine_case_code' => $case->code,
                                                                'nro_identificacion' => $patient->nro_identificacion,
                                                                'age' => $patient->age,
                                                                'sex' => $patient->sex,
                                                                'phone_ppal' => $case->patient_phone,
                                                                'phone_secondary' => $case->patient_phone_2,
                                                                'address' => $case->patient_address,
                                                                'reason_consultation' => $case->reason,
                                                                'background' => $nuevoValorDeSesion,
                                                            ]);
                                                        })
                                                        ->hidden(function () {
                                                            $patient = session()->get('patient');
                                                            $exist = $patient?->noPathologicalHistories()->exists();
                                                            if ($exist) {
                                                                // ... Si el paciente tiene historia registrada lo muestro!
                                                                return false;
                                                            }

                                                            // ... Si el paciente no tiene historia registrada lo oculto!
                                                            return true;
                                                        }),
                                                ])
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('background', $state.toUpperCase());
                                                JS),

                                            Textarea::make('diagnostic_impression')
                                                ->label('Impresión Diagnóstica')
                                                ->autosize()
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('diagnostic_impression', $state.toUpperCase());
                                                JS),

                                            // ...Asignación de Servicio
                                            Fieldset::make('Asignación de Servicio y Actualización de Priroridad')
                                                ->hidden(function (Get $get) {
                                                    if ($get('feedbackOne') == false) {
                                                        return false;
                                                    }

                                                    return true;
                                                })
                                                ->schema([
                                                    Select::make('telemedicine_service_list_id')
                                                        ->label('Tipo de Servicio')
                                                        ->live()
                                                        ->default($defaultTelemedicineServiceListId)
                                                        ->disabled($isTelemedicineServiceListIdLocked)
                                                        ->dehydrated(true)
                                                        ->options(fn (): array => TelemedicineConsultationClinicalUi::type1Options())
                                                        ->rules([
                                                            fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Type1),
                                                        ])
                                                        ->helperText(function (Get $get, Component $livewire) {
                                                            $state = $get('telemedicine_service_list_id');
                                                            $blocked = ClinicalQuotaFormGuard::helperText(
                                                                $livewire,
                                                                ClinicalServiceChannel::Type1,
                                                                filled($state) ? (int) $state : null,
                                                            );

                                                            if ($blocked !== null) {
                                                                return $blocked;
                                                            }

                                                            $banner = TelemedicineConsultationClinicalUi::bannerMessage();
                                                            if (filled($banner)) {
                                                                return $banner;
                                                            }
                                                            $state = $get('telemedicine_service_list_id');
                                                            $cupo = TelemedicineConsultationClinicalUi::type1Helper(filled($state) ? (int) $state : null);
                                                            if (! filled($state)) {
                                                                return $cupo ?? 'Seleccione un servicio incluido en el plan del afiliado.';
                                                            }
                                                            $service = TelemedicineServiceList::find($state);

                                                            return trim(($service?->description ?? '').' · '.($cupo ?? ''));
                                                        })
                                                        ->afterStateUpdated(function (Set $set, $state, Get $get, Component $livewire): void {
                                                            self::syncServiceListSideEffects($set, $get, $state);

                                                            ClinicalQuotaFormGuard::notifyIfBlocked(
                                                                $livewire,
                                                                ClinicalServiceChannel::Type1,
                                                                filled($state) ? (int) $state : null,
                                                            );
                                                        })
                                                        ->searchable()
                                                        ->required(fn (): bool => TelemedicineConsultationClinicalUi::type1Options() !== []),
                                                    Select::make('telemedicine_service_list_drift_id')
                                                        ->label('Tipo de Servicio de Deriva')
                                                        ->live()
                                                        ->options(fn (Get $get): array => TelemedicineConsultationClinicalUi::type1DriftOptions(
                                                            filled($get('telemedicine_service_list_id')) ? (int) $get('telemedicine_service_list_id') : null
                                                        ))
                                                        ->helperText(fn (Get $get): ?string => TelemedicineConsultationClinicalUi::type1DriftOptions(
                                                            filled($get('telemedicine_service_list_id')) ? (int) $get('telemedicine_service_list_id') : null
                                                        ) === []
                                                            ? 'Este plan no tiene otro servicio tipo 1 distinto para derivar.'
                                                            : 'El seguimiento siempre está disponible: un seguimiento puede derivar a otro seguimiento.')
                                                        ->searchable()
                                                        ->required(fn (Get $get): bool => TelemedicineConsultationClinicalUi::type1DriftOptions(
                                                            filled($get('telemedicine_service_list_id')) ? (int) $get('telemedicine_service_list_id') : null
                                                        ) !== []),
                                                    self::generalServiceSelect(),
                                                    self::complementsCheckboxList(),
                                                    self::informAmdTrigger(),
                                                ])->columnSpanFull()->columns(4),

                                        ])->columnSpanFull()->columns(2),
                                ])->columnSpanFull(),

                            Fieldset::make('Observaciones')
                                ->schema([
                                    Grid::make(4)
                                        ->schema([
                                            Select::make('priorityMonitoring')
                                                ->label('Próximo Seguimiento')
                                                ->required()
                                                ->options([
                                                    30 => '30 minutos',
                                                    60 => '60 minutos',
                                                    90 => '90 minutos',
                                                    120 => '120 minutos',
                                                    150 => '150 minutos',
                                                    180 => '180 minutos',
                                                ]),
                                        ]),
                                    Textarea::make('observations')
                                        ->label('Información Adicional')
                                        ->autosize(),
                                ])->columnSpanFull()->columns(1),

                            self::medicalSuppliesFieldset($case),

                        ]),

                    Step::make('Cuestionario de Seguimiento')
                        ->description('Seguimiento clínico, servicio y prioridad.')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->hidden(function () use ($countCase) {

                            $action = session()->get('action') ?? null;

                            if ($countCase < 1) {
                                return true;
                            }

                            if (isset($action) && $action == 'edit' && session()->get('status') == 'CONSULTA INICIAL') {
                                return true;
                            }

                            return false;
                        })
                        ->schema([
                            Fieldset::make('Diagnóstico principal')
                                ->schema([
                                    Textarea::make(TelemedicineInitialDiagnosisUpdater::FORM_FIELD)
                                        ->label('Diagnóstico principal de la consulta inicial')
                                        ->helperText('Actualice el diagnóstico registrado en la consulta inicial si evolucionó. El cambio queda en la bitácora del caso.')
                                        ->autosize()
                                        ->required(function () use ($countCase): bool {
                                            $action = session()->get('action') ?? null;

                                            if ($countCase < 1) {
                                                return false;
                                            }

                                            if (isset($action) && $action == 'edit' && session()->get('status') == 'CONSULTA INICIAL') {
                                                return false;
                                            }

                                            return true;
                                        })
                                        ->default(fn (): string => filled($caseId)
                                            ? TelemedicineInitialDiagnosisUpdater::currentDiagnosis((int) $caseId)
                                            : '')
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('initial_diagnostic_impression', $state.toUpperCase());
                                                JS),
                                ])->columnSpanFull(),
                            Fieldset::make('Historia clínica de seguimiento')
                                ->schema([
                                    Textarea::make('current_illness_history')
                                        ->label('Historia de la enfermedad actual')
                                        ->helperText('Describa el curso reciente de la enfermedad, síntomas actuales y el contexto clínico de este seguimiento.')
                                        ->autosize()
                                        ->columnSpanFull()
                                        ->required(fn (): bool => self::isFollowUpConsultationContext($countCase))
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('current_illness_history', $state.toUpperCase());
                                                JS),
                                    Textarea::make('patient_evolution')
                                        ->label('Evolución del paciente')
                                        ->helperText('Describa cómo ha evolucionado el paciente desde la consulta previa o el último seguimiento.')
                                        ->autosize()
                                        ->columnSpanFull()
                                        ->required(fn (): bool => self::isFollowUpConsultationContext($countCase))
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('patient_evolution', $state.toUpperCase());
                                                JS),
                                ])->columnSpanFull(),
                            // ...Preguntas
                            Fieldset::make('Preguntas de Seguimiento')
                                ->schema([
                                    Textarea::make('cuestion_1')
                                        ->label('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
                                        ->live()
                                        ->autosize()
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_1', $state.toUpperCase());
                                                JS),
                                    Textarea::make('cuestion_2')
                                        ->label('2.- ¿COMO HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                                        ->autosize()
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_2', $state.toUpperCase());
                                                JS),
                                    Textarea::make('cuestion_3')
                                        ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
                                        ->autosize()
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_3', $state.toUpperCase());
                                                JS),
                                    Textarea::make('cuestion_4')
                                        ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
                                        ->autosize()
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_4', $state.toUpperCase());
                                                JS),
                                    Textarea::make('cuestion_5')
                                        ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
                                        ->autosize()
                                        ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_5', $state.toUpperCase());
                                                JS),
                                ])->columnSpanFull()->columns(2),

                            Section::make('Estatus del caso')
                                ->description('Indica si esta consulta cierra el seguimiento con alta médica o si el paciente continúa con asignación de servicio y complementos.')
                                ->icon(Heroicon::OutlinedFlag)
                                ->iconColor('warning')
                                ->schema([
                                    ToggleButtons::make('feedbackOne')
                                        ->label('¿Dar de alta al paciente en esta sesión?')
                                        ->helperText(
                                            $dischargeBlockedMessage
                                                ?? 'Alta médica: solo si todos los servicios asociados están finalizados o caducados (sin pendientes ni en gestión). Continuar: podrás definir el siguiente paso asistencial.'
                                        )
                                        ->boolean(
                                            trueLabel: 'Sí — alta médica',
                                            falseLabel: 'No — asignar servicio',
                                        )
                                        ->default(false)
                                        ->grouped()
                                        ->inline(true)
                                        ->live()
                                        ->afterStateUpdated(function (mixed $state, Set $set) use ($caseCanBeDischarged, $dischargeBlockedMessage): void {
                                            if ($state == true && ! $caseCanBeDischarged) {
                                                $set('feedbackOne', false);

                                                Notification::make()
                                                    ->title('Alta médica no disponible')
                                                    ->body($dischargeBlockedMessage ?? 'Hay servicios asociados pendientes o en gestión.')
                                                    ->warning()
                                                    ->send();
                                            }
                                        })
                                        ->colors([
                                            1 => 'success',
                                            0 => 'primary',
                                        ])
                                        ->icons([
                                            1 => Heroicon::OutlinedCheckCircle,
                                            0 => Heroicon::OutlinedArrowPath,
                                        ])
                                        ->columnSpanFull(),
                                ])
                                ->columns(1)
                                ->columnSpanFull()
                                ->extraAttributes([
                                    'class' => 'fi-telemedicine-case-status-section',
                                ]),

                            // ...Asignación de Servicio
                            Fieldset::make('Asignación de Servicio y Actualización de Prioridad')
                                ->visible(function (Get $get) {
                                    if ($get('feedbackOne') == true) {
                                        return false;
                                    }

                                    return true;
                                })
                                ->schema([
                                    Select::make('telemedicine_service_list_id')
                                        ->label('Tipo de Servicio')
                                        ->live()
                                        ->default($defaultTelemedicineServiceListId)
                                        ->disabled($isTelemedicineServiceListIdLocked)
                                        ->dehydrated(true)
                                        ->options(fn (): array => TelemedicineConsultationClinicalUi::type1Options())
                                        ->rules([
                                            fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Type1),
                                        ])
                                        ->helperText(function (Get $get, Component $livewire) {
                                            $state = $get('telemedicine_service_list_id');
                                            $blocked = ClinicalQuotaFormGuard::helperText(
                                                $livewire,
                                                ClinicalServiceChannel::Type1,
                                                filled($state) ? (int) $state : null,
                                            );

                                            if ($blocked !== null) {
                                                return $blocked;
                                            }

                                            $banner = TelemedicineConsultationClinicalUi::bannerMessage();
                                            if (filled($banner)) {
                                                return $banner;
                                            }
                                            $state = $get('telemedicine_service_list_id');
                                            $cupo = TelemedicineConsultationClinicalUi::type1Helper(filled($state) ? (int) $state : null);
                                            if (! filled($state)) {
                                                return $cupo ?? 'Seleccione un servicio incluido en el plan del afiliado.';
                                            }
                                            $service = TelemedicineServiceList::find($state);

                                            return trim(($service?->description ?? '').' · '.($cupo ?? ''));
                                        })
                                        ->afterStateUpdated(function (Set $set, $state, Get $get, Component $livewire): void {
                                            self::syncServiceListSideEffects($set, $get, $state);

                                            ClinicalQuotaFormGuard::notifyIfBlocked(
                                                $livewire,
                                                ClinicalServiceChannel::Type1,
                                                filled($state) ? (int) $state : null,
                                            );
                                        })
                                        ->searchable()
                                        ->required(fn (): bool => TelemedicineConsultationClinicalUi::type1Options() !== []),
                                    Select::make('telemedicine_service_list_drift_id')
                                        ->label('Tipo de Servicio de Deriva')
                                        ->live()
                                        ->options(fn (Get $get): array => TelemedicineConsultationClinicalUi::type1DriftOptions(
                                            filled($get('telemedicine_service_list_id')) ? (int) $get('telemedicine_service_list_id') : null
                                        ))
                                        ->helperText(fn (Get $get): ?string => TelemedicineConsultationClinicalUi::type1DriftOptions(
                                            filled($get('telemedicine_service_list_id')) ? (int) $get('telemedicine_service_list_id') : null
                                        ) === []
                                            ? 'Este plan no tiene otro servicio tipo 1 distinto para derivar.'
                                            : 'El seguimiento siempre está disponible: un seguimiento puede derivar a otro seguimiento.')
                                        ->nullable()
                                        ->searchable(),
                                    self::generalServiceSelect(),
                                    self::complementsCheckboxList(),
                                    self::informAmdTrigger(),
                                ])->columnSpanFull()->columns(3),

                            Fieldset::make('Observaciones')
                                ->visible(function (Get $get) {
                                    if ($get('feedbackOne') == true) {
                                        return false;
                                    }

                                    return true;
                                })
                                ->schema([
                                    Grid::make(4)
                                        ->schema([
                                            Select::make('priorityMonitoring')
                                                ->label('Próximo Seguimiento')
                                                ->required()
                                                ->options([
                                                    30 => '30 minutos',
                                                    60 => '60 minutos',
                                                    90 => '90 minutos',
                                                    120 => '120 minutos',
                                                    150 => '150 minutos',
                                                    180 => '180 minutos',
                                                    24 => '24 horas',
                                                    48 => '48 horas',
                                                    72 => '72 horas',
                                                ]),
                                        ]),
                                    Textarea::make('observations')
                                        ->label('Observaciones')
                                        ->autosize(),
                                ])->columnSpanFull()->columns(1),

                            self::medicalSuppliesFieldset($case),
                        ]),

                    Step::make('Medicamentos e Indicaciones')
                        ->description('Inventario TDC, cubierto sin inventario (Operaciones) o no cubierto.')
                        ->icon(Heroicon::OutlinedBeaker)
                        ->hidden(fn (Get $get) => $get('feedbackOne') == true || ! in_array(1, $get('complements')))
                        ->schema([
                            LivewireField::make('medicamentos_step_modal_trigger')
                                ->component(\App\Livewire\Forms\MedicamentosStepModalTrigger::class)
                                ->dehydrated(false)
                                ->hiddenLabel()
                                ->columnSpanFull(),
                            Repeater::make('medications')
                                ->table([
                                    TableColumn::make('Inventario TDC')->width('16%'),
                                    TableColumn::make('Cubierto (Operaciones)')->width('16%'),
                                    TableColumn::make('No cubierto')->width('16%'),
                                    TableColumn::make('Indicaciones')->width('27%'),
                                    TableColumn::make('Cantidad')->width('12%'),
                                    TableColumn::make('Duración(en días)')->width('13%'),
                                ])
                                ->rules([
                                    function (): \Closure {
                                        return function (string $attribute, mixed $value, \Closure $fail): void {
                                            if (! is_array($value)) {
                                                return;
                                            }
                                            $rowNumber = 1;
                                            foreach ($value as $row) {
                                                if (is_array($row)) {
                                                    $exclusiveError = TelemedicineMedicationCoverage::exclusiveSourceError($row, $rowNumber);
                                                    if ($exclusiveError !== null) {
                                                        $fail($exclusiveError);
                                                    }

                                                    $hasInventory = TelemedicineMedicationCoverage::rowHasInventory($row);
                                                    if ($hasInventory && (! filled($row['quantity'] ?? null) || (int) $row['quantity'] < 1)) {
                                                        $fail(__('En la fila :n debe indicar la cantidad a entregar (mínimo 1) cuando selecciona inventario TDC.', ['n' => $rowNumber]));
                                                    }
                                                }
                                                $rowNumber++;
                                            }
                                        };
                                    },
                                ])
                                ->schema([
                                    Select::make('operation_inventory_id')
                                        ->options(function () use ($case): array {
                                            $caseModel = self::caseWithDoctor($case);

                                            return TelemedicineMedicationInventoryOptions::optionsForCase(
                                                $caseModel,
                                                $caseModel?->telemedicineDoctor,
                                            );
                                        })
                                        ->getSearchResultsUsing(function (string $search) use ($case): array {
                                            $caseModel = self::caseWithDoctor($case);

                                            return TelemedicineMedicationInventoryOptions::searchOptionsForCase(
                                                $caseModel,
                                                $search,
                                                $caseModel?->telemedicineDoctor,
                                            );
                                        })
                                        ->getOptionLabelUsing(function ($value): ?string {
                                            if (! filled($value)) {
                                                return null;
                                            }

                                            $name = OperationInventory::query()->whereKey($value)->value('name');

                                            return filled($name) ? (string) $name : null;
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live(onBlur: false)
                                        // ->helperText(function () use ($case): ?string {
                                        //     if ($case === null) {
                                        //         return null;
                                        //     }

                                        //     $case->loadMissing('telemedicineDoctor');

                                        //     if (TelemedicineMedicationInventoryOptions::shouldDeductInventory(
                                        //         $case->telemedicineDoctor,
                                        //         $case,
                                        //     )) {
                                        //         $warehouse = TelemedicineMedicationInventoryOptions::warehouseNameForBelongsTo($case->belongs_to);

                                        //         return filled($warehouse)
                                        //             ? "Inventario del almacén {$warehouse} (categoría Medicamento, existencia > 0)."
                                        //             : null;
                                        //     }

                                        //     if (TelemedicineMedicationInventoryOptions::doctorBelongsToProvider($case->telemedicineDoctor)) {
                                        //         return 'Catálogo de medicamentos (sin duplicados). No descuenta inventario.';
                                        //     }

                                        //     return null;
                                        // })
                                        ->afterStateUpdated(function ($state, Set $set): void {
                                            if (filled($state)) {
                                                $set('covered_medicines', null);
                                                $set('medicines', null);
                                                $set('quantity', 1);
                                            }
                                        }),
                                    TextInput::make('covered_medicines')
                                        ->placeholder('Cubierto, sin inventario')
                                        ->live(onBlur: false)
                                        ->afterStateUpdated(function ($state, Set $set): void {
                                            if (filled($state)) {
                                                $set('operation_inventory_id', null);
                                                $set('medicines', null);
                                            }
                                        })
                                        ->afterStateUpdatedJs(<<<'JS'
                                        $set('covered_medicines', $state.toUpperCase());
                                    JS),
                                    TextInput::make('medicines')
                                        ->placeholder('No cubierto')
                                        ->live(onBlur: false)
                                        ->afterStateUpdated(function ($state, Set $set): void {
                                            if (filled($state)) {
                                                $set('operation_inventory_id', null);
                                                $set('covered_medicines', null);
                                            }
                                        })
                                        ->afterStateUpdatedJs(<<<'JS'
                                        $set('medicines', $state.toUpperCase());
                                    JS),
                                    TextInput::make('indications')
                                        // ->helperText('Ingrese las indicaciones del medicamento aquí, si no hay indicaciones, ingrese "NINGUNA"')
                                        ->afterStateUpdatedJs(<<<'JS'
                                        $set('indications', $state.toUpperCase());
                                    JS),
                                    TextInput::make('quantity')
                                        ->numeric()
                                        ->integer()
                                        ->minValue(1)
                                        ->default(1)
                                        ->live(onBlur: false)
                                        ->required(fn (Get $get): bool => filled($get('operation_inventory_id')))
                                        // ->helperText(function (Get $get) use ($case): ?string {
                                        //     if (! filled($get('operation_inventory_id'))) {
                                        //         return 'Opcional. Medicamento manual/no cubierto: no descuenta inventario.';
                                        //     }

                                        //     if ($case === null) {
                                        //         return 'Cantidad a entregar al paciente (se descuenta de existencia).';
                                        //     }

                                        //     $case->loadMissing('telemedicineDoctor');

                                        //     if (! TelemedicineMedicationInventoryOptions::shouldDeductInventory(
                                        //         $case->telemedicineDoctor,
                                        //         $case,
                                        //     )) {
                                        //         return 'Cantidad a entregar (catálogo proveedor; no descuenta inventario).';
                                        //     }

                                        //     $existence = (int) (OperationInventory::query()
                                        //         ->whereKey($get('operation_inventory_id'))
                                        //         ->value('existence') ?? 0);

                                        //     return $existence > 0
                                        //         ? "Disponible: {$existence}. Esta cantidad se restará de la existencia."
                                        //         : 'Cantidad a entregar al paciente (se descuenta de existencia).';
                                        // })
                                        ->rule(function (Get $get) use ($case): \Closure {
                                            return function (string $attribute, mixed $value, \Closure $fail) use ($get, $case): void {
                                                if (! filled($get('operation_inventory_id'))) {
                                                    return;
                                                }

                                                $qty = (int) $value;

                                                if ($qty < 1) {
                                                    $fail('Debe indicar la cantidad a entregar (mínimo 1).');

                                                    return;
                                                }

                                                if ($case === null) {
                                                    return;
                                                }

                                                $case->loadMissing('telemedicineDoctor');

                                                if (! TelemedicineMedicationInventoryOptions::shouldDeductInventory(
                                                    $case->telemedicineDoctor,
                                                    $case,
                                                )) {
                                                    return;
                                                }

                                                $existence = (int) (OperationInventory::query()
                                                    ->whereKey($get('operation_inventory_id'))
                                                    ->value('existence') ?? 0);

                                                if ($existence > 0 && $qty > $existence) {
                                                    $fail("La cantidad no puede superar la existencia en inventario (máximo {$existence} unidades).");
                                                }
                                            };
                                        }),
                                    TextInput::make('duration')
                                        // ->helperText('Ingrese la duración del medicamento en días')
                                        ->numeric()
                                        ->regex('/^[0-9]*$/')
                                        ->required(),
                                ]),
                        ]),

                    Step::make('Laboratorios y Estudios de Imagenología')
                        ->description('Laboratorios e imagenología cubiertos y no cubiertos.')
                        ->icon(Heroicon::OutlinedPhoto)
                        ->hidden(fn (Get $get) => $get('feedbackOne') == true || ! in_array(2, $get('complements')))
                        ->schema([
                            // ...
                            Grid::make()
                                ->schema([
                                    Fieldset::make('Exámenes Laboratorios')
                                        ->schema([
                                            Select::make('labs')
                                                ->label('Laboratorios (CUBIERTOS)')
                                                ->options(TelemedicineListLaboratory::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                                ->multiple()
                                                ->rules([
                                                    fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Laboratory),
                                                ])
                                                ->afterStateUpdated(function (Component $livewire): void {
                                                    ClinicalQuotaFormGuard::notifyIfBlocked($livewire, ClinicalServiceChannel::Laboratory);
                                                })
                                                ->live(onBlur: true)
                                                ->helperText(fn (Component $livewire): ?string => ClinicalQuotaFormGuard::helperText($livewire, ClinicalServiceChannel::Laboratory)
                                                    ?? TelemedicineConsultationClinicalUi::channelHelper(ClinicalServiceChannel::Laboratory)
                                                    ?? 'Seleccione el/los exámenes de Laboratorio que requiera el paciente'),
                                            Select::make('other_labs')
                                                ->label('Otros Laboratorio (NO CUBIERTOS)')
                                                ->options(TelemedicineListLaboratory::where('type', 'NO CUBIERTO')->get()->pluck('name', 'name'))
                                                ->multiple()
                                                ->rules([
                                                    fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Laboratory),
                                                ])
                                                ->afterStateUpdated(function (Component $livewire): void {
                                                    ClinicalQuotaFormGuard::notifyIfBlocked($livewire, ClinicalServiceChannel::Laboratory);
                                                })
                                                ->live(onBlur: true)
                                                ->helperText(fn (Component $livewire): ?string => ClinicalQuotaFormGuard::helperText($livewire, ClinicalServiceChannel::Laboratory)
                                                    ?? 'Seleccione el/los exámenes de Laboratorio que requiera el paciente'),
                                        ])->columns(1),
                                    Fieldset::make('Imagenología')
                                        ->schema([
                                            Select::make('studies')
                                                ->label('Estudios de Imágenes (CUBIERTOS)')
                                                ->live()
                                                ->options(TelemedicineListStudy::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                                ->multiple()
                                                ->rules([
                                                    fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Imaging),
                                                ])
                                                ->afterStateUpdated(function (Component $livewire): void {
                                                    ClinicalQuotaFormGuard::notifyIfBlocked($livewire, ClinicalServiceChannel::Imaging);
                                                })
                                                ->live(onBlur: true)
                                                ->helperText(fn (Component $livewire): ?string => ClinicalQuotaFormGuard::helperText($livewire, ClinicalServiceChannel::Imaging)
                                                    ?? TelemedicineConsultationClinicalUi::channelHelper(ClinicalServiceChannel::Imaging)
                                                    ?? 'Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                            Select::make('other_studies')
                                                ->label(' Otros Estudios de Imágenes (NO CUBIERTOS)')
                                                ->live()
                                                ->options(TelemedicineListStudy::where('type', 'NO CUBIERTO')->get()->pluck('name', 'name'))
                                                ->multiple()
                                                ->rules([
                                                    fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Imaging),
                                                ])
                                                ->afterStateUpdated(function (Component $livewire): void {
                                                    ClinicalQuotaFormGuard::notifyIfBlocked($livewire, ClinicalServiceChannel::Imaging);
                                                })
                                                ->live(onBlur: true)
                                                ->helperText(fn (Component $livewire): ?string => ClinicalQuotaFormGuard::helperText($livewire, ClinicalServiceChannel::Imaging)
                                                    ?? 'Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                        ])->columnSpan(2)->columns(1),
                                    // ...
                                ])->columns(3),
                        ]),

                    Step::make('Interconsulta con Especialista')
                        ->description('Selecciona especialistas según corresponda.')
                        ->icon(Heroicon::OutlinedUserGroup)
                        ->hidden(fn (Get $get) => $get('feedbackOne') == true || ! in_array(3, $get('complements')))
                        ->schema([
                            Placeholder::make('specialist_clinical_usage_notice')
                                ->hiddenLabel()
                                ->content(TelemedicineConsultationClinicalUi::SPECIALIST_NOT_CONTEMPLATED_MESSAGE)
                                ->visible(fn (): bool => ! TelemedicineConsultationClinicalUi::specialistIsContemplated())
                                ->columnSpanFull(),
                            Fieldset::make()
                                ->schema([
                                    Select::make('consult_specialist')
                                        ->label('Interconsultas Especialistas para Patologías Agudas')
                                        ->options(TelemedicineListSpecialist::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                        ->multiple()
                                        ->rules([
                                            fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Specialist),
                                        ])
                                        ->afterStateUpdated(function (Component $livewire): void {
                                            ClinicalQuotaFormGuard::notifyIfBlocked($livewire, ClinicalServiceChannel::Specialist);
                                        })
                                        ->live(onBlur: true)
                                        ->helperText(fn (Component $livewire): ?string => ClinicalQuotaFormGuard::helperText($livewire, ClinicalServiceChannel::Specialist)
                                            ?? 'Interconsultas cubiertas por el plan.'),
                                    Select::make('other_specialist')
                                        ->label('Otros Especialistas') // BVA
                                        ->options(fn () => TelemedicineListSpecialist::uncoveredNames())
                                        ->multiple()
                                        ->rules([
                                            fn (Component $livewire): \Closure => ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Specialist),
                                        ])
                                        ->afterStateUpdated(function (Component $livewire): void {
                                            ClinicalQuotaFormGuard::notifyIfBlocked($livewire, ClinicalServiceChannel::Specialist);
                                        })
                                        ->live(onBlur: true)
                                        ->helperText(fn (Component $livewire): ?string => ClinicalQuotaFormGuard::helperText($livewire, ClinicalServiceChannel::Specialist)
                                            ?? 'Especialistas no cubiertos por el plan.'),
                                ])->columnSpanFull()->columns(2),
                        ]),
                ])
                    ->previousAction(fn (Action $action): Action => $action
                        ->color('gray')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                        ]))
                    ->nextAction(fn (Action $action): Action => $action
                        ->color('primary')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                        ]))
                    ->extraAttributes([
                        'class' => self::WIZARD_IOS_CLASS,
                    ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="create,save"
                        class="fi-btn fi-size-lg aviso-btn-ios-success !rounded-full px-8 py-3 text-base font-semibold tracking-tight shadow-md transition-all duration-200 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <span
                            wire:loading.remove
                            wire:target="create,save"
                            class="inline-flex items-center justify-center"
                        >
                            Registrar consulta
                        </span>
                        <span
                            wire:loading.flex
                            wire:target="create,save"
                            class="hidden items-center justify-center gap-2"
                        >
                            <svg class="h-5 w-5 shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Registrando…</span>
                        </span>
                    </button>
                BLADE)))
                    ->hidden(fn () => session()->get('redCode'))
                    ->columnSpanFull(),
            ]);
    }
}
