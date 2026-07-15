<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Actions;

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
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            ->action(function (TelemedicinePatient $record, array $data): void {
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
                            $createdServices[$specificService] = $service->id;
                        }

                        foreach (self::categories() as $key => $config) {
                            $covered = $selections[$key][self::COVERED];
                            $nonCovered = $selections[$key][self::NOT_COVERED];

                            if ($covered === [] && $nonCovered === []) {
                                continue;
                            }

                            $service = self::createCoordinationService($record, $case, $config['specific_service']);

                            self::createItems($record, $case, $service, $config, $covered, self::COVERED);
                            self::createItems($record, $case, $service, $config, $nonCovered, self::NOT_COVERED);

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
                    CheckboxList::make(self::STANDALONE_SERVICES_FIELD)
                        ->label('Servicios disponibles')
                        ->options(self::standaloneServiceOptions())
                        ->bulkToggleable()
                        ->columns(2)
                        ->helperText('Cada servicio seleccionado genera una solicitud en Coordinación de Servicios.'),
                ]),
        ];

        foreach (self::categories() as $config) {
            $sections[] = Section::make($config['label'])
                ->icon($config['icon'])
                ->columns(2)
                ->schema([
                    Select::make($config['covered_field'])
                        ->label('Cubiertos')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => self::catalogOptions(
                            $config['catalog'],
                            self::COVERED,
                            $config['covered_type_column'] ?? 'type',
                        ))
                        ->helperText('Ítems con cobertura. Habilitan la creación de la orden de servicio.'),
                    Select::make($config['non_covered_field'])
                        ->label('No cubiertos')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => self::catalogOptions(
                            $config['catalog'],
                            self::NOT_COVERED,
                            $config['non_covered_type_column'] ?? 'type',
                        ))
                        ->helperText('Ítems sin cobertura. Requieren cotización en la gestión.'),
                ]);
        }

        return $sections;
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
     * @return array<string, array{label: string, icon: string, catalog: class-string, model: class-string, column: string, specific_service: string, covered_field: string, non_covered_field: string, covered_type_column?: string, non_covered_type_column?: string}>
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
                'covered_field' => 'labs_covered',
                'non_covered_field' => 'labs_non_covered',
            ],
            'studies' => [
                'label' => 'Estudios',
                'icon' => 'heroicon-o-photo',
                'catalog' => TelemedicineListStudy::class,
                'model' => TelemedicinePatientStudy::class,
                'column' => 'study',
                'specific_service' => 'IMAGENOLOGIA',
                'covered_field' => 'studies_covered',
                'non_covered_field' => 'studies_non_covered',
            ],
            'specialists' => [
                'label' => 'Consultas con especialistas',
                'icon' => 'heroicon-o-user-group',
                'catalog' => TelemedicineListSpecialist::class,
                'model' => TelemedicinePatientSpecialty::class,
                'column' => 'specialty',
                'specific_service' => 'ESPECIALISTA',
                'covered_field' => 'specialists_covered',
                'non_covered_field' => 'specialists_non_covered',
                'covered_type_column' => 'type',
                'non_covered_type_column' => 'type_two',
            ],
        ];
    }

    /**
     * @param  class-string  $catalog
     * @return array<string, string>
     */
    private static function catalogOptions(string $catalog, string $type, string $typeColumn = 'type'): array
    {
        return $catalog::query()
            ->where($typeColumn, $type)
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, array<int, string>>>
     */
    private static function collectSelections(array $data): array
    {
        $selections = [];

        foreach (self::categories() as $key => $config) {
            $selections[$key] = [
                self::COVERED => self::normalizeSelection($data[$config['covered_field']] ?? []),
                self::NOT_COVERED => self::normalizeSelection($data[$config['non_covered_field']] ?? []),
            ];
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
     * @param  array<string, array<string, array<int, string>>>  $selections
     */
    private static function selectionsAreEmpty(array $selections): bool
    {
        foreach ($selections as $category) {
            if ($category[self::COVERED] !== [] || $category[self::NOT_COVERED] !== []) {
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
     * @param  array{model: class-string, column: string}  $config
     * @param  array<int, string>  $names
     */
    private static function createItems(
        TelemedicinePatient $record,
        TelemedicineCase $case,
        OperationCoordinationService $service,
        array $config,
        array $names,
        string $type,
    ): void {
        $modelClass = $config['model'];

        foreach ($names as $name) {
            $modelClass::create([
                'telemedicine_patient_id' => $record->id,
                'telemedicine_case_id' => $case->id,
                $config['column'] => $name,
                'type' => $type,
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
