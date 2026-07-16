<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Actions;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Http\Controllers\UtilsController;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineCoverageCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class RegisterTpaRetailServicesAction
{
    private const COVERED = 'CUBIERTO';

    private const NOT_COVERED = 'NO CUBIERTO';

    private const CASE_STATUS = 'TPA/RETAIL';

    private const STANDALONE_SERVICES_FIELD = 'standalone_services';

    /**
     * Servicios de alto nivel (sin catálogo de ítems) seleccionables por el analista.
     *
     * @var list<string>
     */
    private const STANDALONE_SPECIFIC_SERVICES = [
        'TELEMEDICINA',
        'AMD (ASISTENCIA MEDICA DOMICILIARIA)',
        'TRASLADO EN AMBULANCIA',
        'CONSULTA ONLINE CON MEDICO ESPECIALISTA',
        'URGEN CARE',
        'APS',
        'INGRESO A CLINICA',
        'LECTURA DE RESULTADOS (LABORATORIO(S))',
        'LECTURA DE RESULTADOS (IMAGENOLOGIA)',
    ];

    public static function make(): Action
    {
        return Action::make('register_tpa_retail_services')
            ->label('Registrar servicios TPA/RETAIL')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('success')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalHeading('Registro de servicios TPA/RETAIL')
            ->modalDescription('Seleccione servicios adicionales, laboratorios, estudios y consultas con especialistas. Cada selección se enviará a Coordinación de Servicios para su gestión.')
            ->modalSubmitActionLabel('Registrar servicios')
            ->form(self::formSchema())
            ->action(function (TelemedicinePatient $record, array $data, Component $livewire): void {
                $selections = self::collectSelections($data);
                $standaloneServices = self::normalizeStandaloneServices($data[self::STANDALONE_SERVICES_FIELD] ?? []);

                if (self::selectionsAreEmpty($selections) && $standaloneServices === []) {
                    Notification::make()
                        ->title('Sin ítems seleccionados')
                        ->body('Seleccione al menos un servicio, laboratorio, estudio o consulta con especialista.')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $createdServices = [];
                    $case = null;

                    DB::transaction(function () use ($record, $selections, $standaloneServices, &$createdServices, &$case): void {
                        $case = self::createCase($record);

                        foreach ($standaloneServices as $specificService) {
                            $service = self::createCoordinationService($record, $case, $specificService);
                            self::seedStandaloneManagementItem($record, $case, $service, $specificService);
                            $createdServices[$specificService] = $service->id;
                        }

                        foreach (self::categories() as $key => $config) {
                            $names = $selections[$key];

                            if ($names === []) {
                                continue;
                            }

                            $service = self::createCoordinationService($record, $case, $config['specific_service']);

                            self::createItems($record, $case, $service, $config, $names);

                            $createdServices[$config['specific_service']] = $service->id;
                        }
                    });

                    SecurityAudit::log('AUDIT_OPERATIONS_TPA_RETAIL_SERVICES_REGISTERED', 'operations.telemedicine-patients.register-tpa-retail-services', [
                        'telemedicine_patient_id' => $record->id,
                        'patient_name' => $record->full_name,
                        'telemedicine_case_id' => $case?->id,
                        'telemedicine_case_code' => $case?->code,
                        'created_services' => $createdServices,
                    ]);

                    Notification::make()
                        ->title('Servicios registrados')
                        ->body('Los servicios TPA/RETAIL fueron enviados a Coordinación de Servicios para su gestión.')
                        ->success()
                        ->send();

                    $livewire->redirect(self::medicalServicesIndexUrl($case));
                } catch (\Throwable $exception) {
                    Log::error('Error al registrar servicios TPA/RETAIL: '.$exception->getMessage(), [
                        'telemedicine_patient_id' => $record->id,
                        'exception' => $exception,
                    ]);

                    SecurityAudit::log('AUDIT_OPERATIONS_TPA_RETAIL_SERVICES_FAILED', 'operations.telemedicine-patients.register-tpa-retail-services', [
                        'telemedicine_patient_id' => $record->id,
                        'patient_name' => $record->full_name,
                        'error' => $exception->getMessage(),
                    ]);

                    Notification::make()
                        ->title('No se pudieron registrar los servicios')
                        ->body('Ocurrió un error al registrar los servicios. Intente nuevamente.')
                        ->danger()
                        ->send();
                }
            });
    }

    public static function medicalServicesIndexUrl(?TelemedicineCase $case = null): string
    {
        $url = OperationCoordinationServiceResource::getUrl('index', [
            'tab' => 'pendiente',
        ]);

        if (! $case instanceof TelemedicineCase) {
            return $url;
        }

        $groupTitle = self::caseGroupTitle($case);

        if ($groupTitle === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'expand_group' => $groupTitle,
        ]);
    }

    public static function caseGroupTitle(TelemedicineCase $case): string
    {
        $code = mb_strtoupper(trim((string) ($case->code ?? '')));

        if ($code === '') {
            return '';
        }

        $patientName = trim((string) ($case->patient_name ?? ''));

        return $patientName !== '' ? $code.' · '.$patientName : $code;
    }

    /**
     * @return array<int, Section>
     */
    private static function formSchema(): array
    {
        $sections = [
            Section::make('Servicios')
                ->icon('heroicon-o-clipboard-document-list')
                ->description('Servicios adicionales que el analista puede registrar sin detalle de laboratorios, estudios o especialistas.')
                ->schema([
                    Select::make(self::STANDALONE_SERVICES_FIELD)
                        ->label('Servicios disponibles')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(self::standaloneServiceOptions())
                        ->helperText('Cada servicio seleccionado genera una solicitud en Coordinación de Servicios.'),
                ]),
        ];

        foreach (self::categories() as $config) {
            $sections[] = Section::make($config['label'])
                ->icon($config['icon'])
                ->schema([
                    Select::make($config['field'])
                        ->label($config['label'])
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => self::catalogOptions($config['catalog']))
                        ->helperText('Se listan todos los ítems del catálogo. La cobertura se resuelve automáticamente al registrar.'),
                ]);
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    public static function standaloneSpecificServices(): array
    {
        return self::STANDALONE_SPECIFIC_SERVICES;
    }

    /**
     * @return array<string, string>
     */
    public static function standaloneServiceOptions(): array
    {
        return collect(self::STANDALONE_SPECIFIC_SERVICES)
            ->mapWithKeys(static fn (string $service): array => [$service => $service])
            ->all();
    }

    public static function isStandaloneSpecificService(?string $specificService): bool
    {
        $specificService = trim((string) $specificService);

        return $specificService !== ''
            && in_array($specificService, self::STANDALONE_SPECIFIC_SERVICES, true);
    }

    public static function isTpaRetailStandaloneCoordination(OperationCoordinationService $record): bool
    {
        return mb_strtoupper(trim((string) $record->servicie)) === 'TPA/RETAIL'
            && self::isStandaloneSpecificService($record->specific_service);
    }

    /**
     * Garantiza un ítem gestionable (no cubierto) para cotizar el servicio standalone.
     */
    public static function ensureStandaloneManagementItem(OperationCoordinationService $record): void
    {
        if (! self::isTpaRetailStandaloneCoordination($record)) {
            return;
        }

        $specificService = trim((string) $record->specific_service);

        $exists = $record->telemedicinePatientSpecialties()
            ->where('specialty', $specificService)
            ->exists();

        if ($exists) {
            return;
        }

        TelemedicinePatientSpecialty::query()->create([
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'telemedicine_doctor_id' => $record->telemedicine_doctor_id,
            'telemedicine_consultation_patient_id' => $record->telemedicine_consultation_patient_id,
            'type' => self::NOT_COVERED,
            'specialty' => $specificService,
            'assigned_by' => Auth::id(),
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $record->id,
        ]);
    }

    private static function seedStandaloneManagementItem(
        TelemedicinePatient $record,
        TelemedicineCase $case,
        OperationCoordinationService $service,
        string $specificService,
    ): void {
        TelemedicinePatientSpecialty::query()->create([
            'telemedicine_patient_id' => $record->id,
            'telemedicine_case_id' => $case->id,
            'type' => self::NOT_COVERED,
            'specialty' => $specificService,
            'assigned_by' => Auth::id(),
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $service->id,
        ]);
    }

    /**
     * @return list<string>
     */
    private static function normalizeStandaloneServices(mixed $values): array
    {
        $allowed = self::STANDALONE_SPECIFIC_SERVICES;

        return collect(is_array($values) ? $values : [])
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{label: string, icon: string, catalog: class-string, model: class-string, column: string, specific_service: string, field: string, coverage_resolver: callable(string): bool}>
     */
    private static function categories(): array
    {
        return [
            'labs' => [
                'label' => 'Laboratorios',
                'icon' => 'heroicon-o-beaker',
                'catalog' => TelemedicineListLaboratory::class,
                'model' => TelemedicinePatientLab::class,
                'column' => 'laboratory',
                'specific_service' => 'LABORATORIOS',
                'field' => 'labs',
                'coverage_resolver' => static fn (string $name): bool => TelemedicineCoverageCatalog::laboratoryIsCovered($name),
            ],
            'studies' => [
                'label' => 'Estudios',
                'icon' => 'heroicon-o-photo',
                'catalog' => TelemedicineListStudy::class,
                'model' => TelemedicinePatientStudy::class,
                'column' => 'study',
                'specific_service' => 'IMAGENOLOGIA',
                'field' => 'studies',
                'coverage_resolver' => static fn (string $name): bool => TelemedicineCoverageCatalog::studyIsCovered($name),
            ],
            'specialists' => [
                'label' => 'Consultas con especialistas',
                'icon' => 'heroicon-o-user-group',
                'catalog' => TelemedicineListSpecialist::class,
                'model' => TelemedicinePatientSpecialty::class,
                'column' => 'specialty',
                'specific_service' => 'ESPECIALISTA',
                'field' => 'specialists',
                'coverage_resolver' => static fn (string $name): bool => TelemedicineCoverageCatalog::specialistIsCovered($name),
            ],
        ];
    }

    /**
     * @param  class-string  $catalog
     * @return array<string, string>
     */
    private static function catalogOptions(string $catalog): array
    {
        return $catalog::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, string>>
     */
    private static function collectSelections(array $data): array
    {
        $selections = [];

        foreach (self::categories() as $key => $config) {
            $selections[$key] = self::normalizeSelection($data[$config['field']] ?? []);
        }

        return $selections;
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeSelection(mixed $values): array
    {
        return collect(is_array($values) ? $values : [])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, string>>  $selections
     */
    private static function selectionsAreEmpty(array $selections): bool
    {
        foreach ($selections as $names) {
            if ($names !== []) {
                return false;
            }
        }

        return true;
    }

    private static function createCase(TelemedicinePatient $record): TelemedicineCase
    {
        return TelemedicineCase::create([
            'code' => UtilsController::generateCaseCode(),
            'telemedicine_patient_id' => $record->id,
            'patient_name' => $record->full_name,
            'patient_age' => $record->age,
            'patient_sex' => $record->sex,
            'patient_phone' => $record->phone,
            'patient_address' => $record->address,
            'patient_country_id' => $record->country_id,
            'patient_state_id' => $record->state_id,
            'patient_city_id' => $record->city_id,
            'reason' => 'SERVICIOS TPA/RETAIL',
            'status' => self::CASE_STATUS,
            'assigned_by' => Auth::user()?->name,
            'managed_by' => $record->managed_by,
            'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
        ]);
    }

    private static function createCoordinationService(TelemedicinePatient $record, TelemedicineCase $case, string $specificService): OperationCoordinationService
    {
        $userName = Auth::user()?->name ?? '...';

        return OperationCoordinationService::create([
            'telemedicine_patient_id' => $record->id,
            'telemedicine_case_id' => $case->id,
            'date_solicitud' => now(),
            'date_service' => now(),
            'business_line_id' => $record->business_line_id,
            'business_unit_id' => $record->business_unit_id,
            'reference_number' => $case->code ?? self::buildReferenceNumber($record),
            'status' => 'PENDIENTE',
            'patient' => $record->full_name,
            'ci_patient' => $record->nro_identificacion ?? '...',
            'birth_date_patient' => $record->birth_date,
            'relationship_patient' => 'TITULAR',
            'age_patient' => $record->age,
            'contractor' => $record->afilliation_id === null ? 'CORPORATIVO' : 'INDIVIDUAL',
            'state_id' => $record->state_id,
            'city_id' => $record->city_id,
            'address' => $record->address,
            'phone_holder' => $record->phone,
            'symptoms_diagnosis' => 'SERVICIO TPA/RETAIL',
            'servicie' => 'TPA/RETAIL',
            'specific_service' => $specificService,
            'type_negotiation' => '...',
            'status_negotiation' => '...',
            'neto' => 0.00,
            'porcen_tdec' => 0,
            'quote_price' => 0.00,
            'negotiation' => '...',
            'porcen_discount' => 0,
            'price_discount' => 0.00,
            'quote_number' => '...',
            'approved_number' => '...',
            'service_order_number' => 0,
            'bill_number' => '...',
            'bill_price' => 0.00,
            'bill_date' => now()->format('d/m/Y'),
            'incidence' => 0,
            'negotiation_description' => '...',
            'qc_description' => '...',
            'observations' => '...',
            'created_by' => $userName,
            'updated_by' => $userName,
            'managed_by' => $record->managed_by,
            'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
        ]);
    }

    /**
     * @param  array{model: class-string, column: string, coverage_resolver: callable(string): bool}  $config
     * @param  array<int, string>  $names
     */
    private static function createItems(
        TelemedicinePatient $record,
        TelemedicineCase $case,
        OperationCoordinationService $service,
        array $config,
        array $names,
    ): void {
        $modelClass = $config['model'];
        $coverageResolver = $config['coverage_resolver'];

        foreach ($names as $name) {
            $modelClass::create([
                'telemedicine_patient_id' => $record->id,
                'telemedicine_case_id' => $case->id,
                $config['column'] => $name,
                'type' => $coverageResolver($name) ? self::COVERED : self::NOT_COVERED,
                'assigned_by' => Auth::id(),
                'status' => 'PENDIENTE',
                'operation_coordination_service_id' => $service->id,
            ]);
        }
    }

    private static function buildReferenceNumber(TelemedicinePatient $record): string
    {
        $base = filled($record->code) ? (string) $record->code : (string) $record->id;

        return 'TPA-'.$base.'-'.now()->format('YmdHis');
    }
}
